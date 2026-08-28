<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryDisplay;
use Voodflow\Vmedia\Support\GalleryPath;
use Voodflow\Vmedia\Tests\TestCase;

class GalleryDisplayTest extends TestCase
{
    public function test_nav_label_disambiguates_nested_library_albums(): void
    {
        $builder = MediaGallery::query()->create([
            'name' => 'Builder',
            'slug' => 'voodbuilder',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);

        $library = MediaGallery::query()->create([
            'name' => 'Library',
            'slug' => 'library',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $builder->getKey(),
            'parent_key' => (int) $builder->getKey(),
            'is_public' => true,
            'sort_order' => 0,
        ]);

        $this->assertSame('Builder › Library', GalleryDisplay::navLabel($library));
        $this->assertSame('Builder', GalleryDisplay::navLabel($builder));
    }

    public function test_order_hierarchically_nests_children_under_groups(): void
    {
        $root = MediaGallery::query()->create([
            'name' => 'Exhibitors',
            'slug' => 'exhibitors',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);

        $child = MediaGallery::query()->create([
            'name' => 'Yamaha',
            'slug' => 'yamaha',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $root->getKey(),
            'parent_key' => (int) $root->getKey(),
            'is_public' => true,
            'sort_order' => 0,
        ]);

        $flat = [
            [
                'id' => (int) $child->getKey(),
                'name' => 'Yamaha',
                'kind' => MediaGallery::KIND_ALBUM,
                'parent_id' => (int) $root->getKey(),
            ],
            [
                'id' => (int) $root->getKey(),
                'name' => 'Exhibitors',
                'kind' => MediaGallery::KIND_GROUP,
                'parent_id' => null,
            ],
        ];

        $ordered = GalleryDisplay::orderHierarchically($flat);

        $this->assertSame('Exhibitors', $ordered[0]['name']);
        $this->assertSame(0, $ordered[0]['depth']);
        $this->assertSame('Yamaha', $ordered[1]['name']);
        $this->assertSame(1, $ordered[1]['depth']);
    }

    public function test_breadcrumb_builds_full_chain(): void
    {
        $group = MediaGallery::query()->create([
            'name' => 'Events',
            'slug' => 'events',
            'kind' => MediaGallery::KIND_GROUP,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);

        $album = MediaGallery::query()->create([
            'name' => 'Library',
            'slug' => 'library',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $group->getKey(),
            'parent_key' => (int) $group->getKey(),
            'is_public' => true,
            'sort_order' => 0,
        ]);

        $this->assertSame('events/library', GalleryPath::toPath($album));
        $this->assertSame('Events › Library', GalleryDisplay::breadcrumb($album));
    }
}
