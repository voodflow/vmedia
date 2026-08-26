<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\AttachmentMeta;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Tests\TestCase;

class HasAttachedMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('vmedia_attachable_dummies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function test_attach_and_read_logical_collections(): void
    {
        Storage::fake('public');

        $record = VmediaAttachableDummy::query()->create(['name' => 'Booth']);
        $logo = MediaLibrary::store(UploadedFile::fake()->image('logo.png', 16, 16));
        $shot = MediaLibrary::store(UploadedFile::fake()->image('shot.jpg', 16, 16));

        $record->attachMediaItem('logo', $logo, replace: true);
        $record->attachMediaItem('gallery', $shot);

        $record->unsetRelation('media');

        $this->assertSame((int) $logo->getKey(), (int) $record->getFirstMedia('logo')?->getKey());
        $this->assertCount(1, $record->getMedia('gallery'));
        $this->assertStringStartsWith('/storage/', $record->getFirstMediaUrl('logo'));
    }

    public function test_detach_abandoned_does_not_delete_vault_row(): void
    {
        Storage::fake('public');

        $record = VmediaAttachableDummy::query()->create(['name' => 'A']);
        $media = MediaLibrary::store(UploadedFile::fake()->image('keep.png', 8, 8));
        $record->attachMediaItem('logo', $media);

        $record->detachAbandonedMedia('logo', []);

        $this->assertCount(0, $record->fresh()->getMedia('logo'));
        $this->assertNotNull($media->fresh());
    }

    public function test_attachment_properties_are_per_record_and_preserved_on_resync(): void
    {
        Storage::fake('public');

        $media = MediaLibrary::store(UploadedFile::fake()->image('shared.png', 12, 12));
        $media->setCaption('Vault caption')->save();

        $alpha = VmediaAttachableDummy::query()->create(['name' => 'Alpha']);
        $beta = VmediaAttachableDummy::query()->create(['name' => 'Beta']);

        $alpha->syncMediaCollection('gallery', [[
            'id' => (int) $media->getKey(),
            'properties' => ['caption' => 'Alpha caption', 'alt' => 'Alpha alt'],
        ]]);

        $beta->syncMediaCollection('gallery', [[
            'id' => (int) $media->getKey(),
            'properties' => ['caption' => 'Beta caption'],
        ]]);

        $alphaMedia = $alpha->fresh()->getFirstMedia('gallery');
        $betaMedia = $beta->fresh()->getFirstMedia('gallery');

        $this->assertNotNull($alphaMedia);
        $this->assertNotNull($betaMedia);
        $this->assertSame('Alpha caption', AttachmentMeta::caption($alphaMedia));
        $this->assertSame('Alpha alt', AttachmentMeta::alt($alphaMedia));
        $this->assertSame('Beta caption', AttachmentMeta::caption($betaMedia));
        $this->assertSame('Vault caption', $media->fresh()->caption());
        $this->assertNull(AttachmentMeta::alt($betaMedia));

        // Resync without properties keeps previous overrides.
        $alpha->syncMediaCollection('gallery', [(int) $media->getKey()]);
        $alphaMedia = $alpha->fresh()->getFirstMedia('gallery');

        $this->assertSame('Alpha caption', AttachmentMeta::caption($alphaMedia));
        $this->assertSame('Alpha alt', AttachmentMeta::alt($alphaMedia));
    }

    public function test_gallery_sync_ordered_media_by_uuid(): void
    {
        Storage::fake('public');
        config()->set('vmedia.duplicates.detect', false);
        config()->set('vmedia.duplicates.reuse', false);

        $gallery = MediaGallery::query()->create([
            'name' => 'Show',
            'is_public' => true,
            'sort_order' => 1,
        ]);

        $a = MediaLibrary::store(UploadedFile::fake()->image('a.png', 8, 8));
        $b = MediaLibrary::store(UploadedFile::fake()->image('b.png', 10, 10));

        $gallery->syncOrderedMedia([(string) $b->uuid, (string) $a->uuid]);

        $this->assertSame(
            [(string) $b->uuid, (string) $a->uuid],
            $gallery->fresh()->orderedMediaUuids(),
        );
    }
}

class VmediaAttachableDummy extends Model
{
    use HasAttachedMedia;

    protected $table = 'vmedia_attachable_dummies';

    protected $guarded = [];
}
