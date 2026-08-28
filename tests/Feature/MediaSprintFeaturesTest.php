<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
use Voodflow\Vmedia\Events\MediaAttached;
use Voodflow\Vmedia\Events\MediaStored;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\MediaUsage;
use Voodflow\Vmedia\Support\ZipImporter;
use Voodflow\Vmedia\Tests\TestCase;
use ZipArchive;

class MediaSprintFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([
            'vmedia_sprint_attachable' => SprintAttachable::class,
        ]);

        Schema::create('vmedia_attachable_dummies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function test_duplicate_upload_reuses_vault_row(): void
    {
        Storage::fake('public');

        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $pathA = storage_path('framework/testing/dup-a.png');
        $pathB = storage_path('framework/testing/dup-b.png');
        file_put_contents($pathA, $bytes);
        file_put_contents($pathB, $bytes);

        $first = MediaLibrary::store(new UploadedFile($pathA, 'same.png', 'image/png', null, true));
        $second = MediaLibrary::store(new UploadedFile($pathB, 'same-again.png', 'image/png', null, true));

        $this->assertSame((int) $first->getKey(), (int) $second->getKey());
        $this->assertSame(1, MediaItem::query()->count());

        @unlink($pathA);
        @unlink($pathB);
    }

    public function test_metadata_alt_focal_and_payload(): void
    {
        Storage::fake('public');

        $media = MediaLibrary::store(UploadedFile::fake()->image('hero.jpg', 20, 20), null, 'Hero');
        $media->setAlt('Hero skyline');
        $media->setCredits('© Cosmolab');
        $media->setFocalPoint(30, 70);
        $media->save();

        $payload = MediaLibrary::toAssetPayload($media->fresh());

        $this->assertSame('Hero skyline', $payload['alt']);
        $this->assertSame('30% 70%', $payload['object_position']);
        $this->assertSame('image', $payload['type']);
    }

    public function test_delete_when_attached_detaches_and_soft_deletes(): void
    {
        Storage::fake('public');
        Event::fake([MediaAttached::class, MediaStored::class]);

        $record = SprintAttachable::query()->create(['name' => 'A']);
        $media = MediaLibrary::store(UploadedFile::fake()->image('logo.png', 8, 8));
        $record->attachMediaItem('logo', $media);

        Event::assertDispatched(MediaStored::class);
        Event::assertDispatched(MediaAttached::class);
        $this->assertTrue(MediaUsage::isUsed($media));

        MediaLibrary::delete($media, force: false);

        $this->assertSame(0, MediaUsage::attachmentCount($media));
        $this->assertTrue($media->fresh()->trashed());
        $this->assertSame(0, $record->fresh()->getMedia('logo')->count());
    }

    public function test_soft_delete_and_restore(): void
    {
        Storage::fake('public');

        $media = MediaLibrary::store(UploadedFile::fake()->image('tmp.png', 8, 8));
        MediaLibrary::delete($media, force: false);

        $this->assertTrue($media->fresh()->trashed());

        MediaLibrary::restore($media->fresh());
        $this->assertFalse($media->fresh()->trashed());
    }

    public function test_files_collection_and_public_gallery_route(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf');
        $media = MediaLibrary::store($pdf);

        $this->assertSame('files', $media->collection_name);
        $this->assertSame('file', MediaLibrary::assetType($media));

        $gallery = MediaGallery::default();
        $gallery->forceFill(['is_public' => true])->save();

        $this->get('/galleries/'.$gallery->slug)->assertOk();
    }

    public function test_zip_import_stores_allowed_files(): void
    {
        Storage::fake('public');

        $zipPath = storage_path('framework/testing/vmedia-test.zip');
        @unlink($zipPath);

        $archive = new ZipArchive;
        $archive->open($zipPath, ZipArchive::CREATE);
        $archive->addFromString('nested/photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        ));
        $archive->addFromString('readme.txt', 'hello');
        $archive->close();

        $result = ZipImporter::import($zipPath, MediaGallery::default());

        $this->assertGreaterThanOrEqual(1, $result['imported']);
        $this->assertNotEmpty($result['items']);
        @unlink($zipPath);
    }

    public function test_orphan_stats_and_prune_dry_run(): void
    {
        Storage::fake('public');

        $media = MediaLibrary::store(UploadedFile::fake()->image('orphan.png', 8, 8));
        $media->galleries()->detach();

        $stats = MediaUsage::stats();
        $this->assertGreaterThanOrEqual(1, $stats['orphans']);

        $this->artisan('vmedia:prune-orphans', ['--days' => 0])
            ->assertSuccessful();

        $this->assertNotNull($media->fresh());

        $this->artisan('vmedia:prune-orphans', ['--days' => 0, '--force' => true])
            ->assertSuccessful();

        $this->assertTrue($media->fresh()->trashed());
    }

    public function test_stats_command(): void
    {
        Storage::fake('public');
        MediaLibrary::store(UploadedFile::fake()->image('a.png', 8, 8));

        $this->artisan('vmedia:stats')->assertSuccessful();
    }
}

class SprintAttachable extends Model
{
    use HasAttachedMedia;

    protected $table = 'vmedia_attachable_dummies';

    protected $guarded = [];
}
