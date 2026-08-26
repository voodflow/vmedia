<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
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
}

class VmediaAttachableDummy extends Model
{
    use HasAttachedMedia;

    protected $table = 'vmedia_attachable_dummies';

    protected $guarded = [];
}
