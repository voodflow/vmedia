<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaTag;
use Voodflow\Vmedia\Support\GalleryAggregate;
use Voodflow\Vmedia\Support\GalleryPath;
use Voodflow\Vmedia\Support\GalleryProvisioner;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Tests\TestCase;

class GalleryHierarchyTest extends TestCase
{
    public function test_group_cannot_attach_media(): void
    {
        $group = MediaGallery::query()->create([
            'name' => '2024',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);

        Storage::fake('public');
        $media = MediaLibrary::store(
            UploadedFile::fake()->image('shot.jpg', 8, 8),
            MediaGallery::default(),
        );

        $this->expectException(ValidationException::class);
        $group->attachMedia([$media]);
    }

    public function test_store_with_folder_attaches_to_resolved_album(): void
    {
        Storage::fake('public');

        $folder = MediaGallery::query()->create([
            'name' => 'Builder',
            'slug' => 'builder-folder',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);

        $album = GalleryProvisioner::ensure([
            'name' => 'Library',
            'slug' => 'library',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent' => $folder,
            'integration_source' => 'test',
            'integration_key' => 'group:'.$folder->getKey().':library',
        ]);

        $media = MediaLibrary::store(
            UploadedFile::fake()->image('hero.jpg', 12, 12),
            $folder,
        );

        $this->assertTrue($media->galleries->contains(fn (MediaGallery $g): bool => (int) $g->getKey() === (int) $album->getKey()));
        $this->assertFalse($media->galleries->contains(fn (MediaGallery $g): bool => (int) $g->getKey() === (int) $folder->getKey()));
    }

    public function test_path_resolution_and_aggregate_descendants(): void
    {
        Storage::fake('public');

        $year = MediaGallery::query()->create([
            'name' => '2024',
            'slug' => '2024',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);

        $albumA = MediaGallery::query()->create([
            'name' => 'Queen',
            'slug' => 'queen',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $year->getKey(),
            'parent_key' => (int) $year->getKey(),
            'is_public' => true,
            'sort_order' => 1,
        ]);

        $albumB = MediaGallery::query()->create([
            'name' => 'Pink Floyd',
            'slug' => 'pink-floyd',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $year->getKey(),
            'parent_key' => (int) $year->getKey(),
            'is_public' => true,
            'sort_order' => 2,
        ]);

        $tag = MediaTag::query()->create(['name' => 'Rock', 'type' => 'genre']);

        $photoA = MediaLibrary::store(UploadedFile::fake()->image('a.jpg', 8, 8), $albumA);
        $photoB = MediaLibrary::store(UploadedFile::fake()->image('b.jpg', 16, 16), $albumB);
        $photoA->tags()->sync([(int) $tag->getKey()]);

        $resolved = GalleryPath::resolve('2024/queen');
        $this->assertNotNull($resolved);
        $this->assertSame((int) $albumA->getKey(), (int) $resolved->getKey());

        $result = GalleryAggregate::paginateMedia($year);
        $this->assertSame(2, $result['meta']['total']);

        $filtered = GalleryAggregate::paginateMedia($year, ['tag_ids' => [(int) $tag->getKey()]]);
        $this->assertSame(1, $filtered['meta']['total']);
    }

    public function test_allowed_tags_inherit_from_parent_group(): void
    {
        $allowed = MediaTag::query()->create(['name' => 'Mario', 'type' => 'photographer']);
        MediaTag::query()->create(['name' => 'Ignored', 'type' => 'custom']);

        $group = MediaGallery::query()->create([
            'name' => '2025',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);
        $group->allowedTags()->sync([(int) $allowed->getKey()]);

        $album = MediaGallery::query()->create([
            'name' => 'Album',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $group->getKey(),
            'parent_key' => (int) $group->getKey(),
            'is_public' => true,
            'sort_order' => 1,
        ]);

        $ids = GalleryPath::effectiveAllowedTagIds($album);
        $this->assertSame([(int) $allowed->getKey()], $ids);
    }

    public function test_provisioner_is_idempotent(): void
    {
        $first = GalleryProvisioner::ensure([
            'name' => 'Edition',
            'slug' => 'edition',
            'kind' => MediaGallery::KIND_GROUP,
            'integration_source' => 'test',
            'integration_key' => 'demo:1',
        ]);

        $second = GalleryProvisioner::ensure([
            'name' => 'Edition duplicate',
            'slug' => 'edition-2',
            'kind' => MediaGallery::KIND_GROUP,
            'integration_source' => 'test',
            'integration_key' => 'demo:1',
        ]);

        $this->assertSame((int) $first->getKey(), (int) $second->getKey());
    }
}
