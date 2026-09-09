<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryUploadTarget;
use Voodflow\Vmedia\Support\Integration\DuplicateLibraryAlbumPruner;
use Voodflow\Vmedia\Support\Integration\PluginVaultRootGroup;
use Voodflow\Vmedia\Tests\TestCase;
use Voodflow\Vmedia\Vmedia;

class DuplicateLibraryAlbumPrunerTest extends TestCase
{
    public function test_prune_removes_duplicate_empty_library_album_under_plugin_folder(): void
    {
        Vmedia::registerPluginVault('voodbuilder', 'voodbuilder', 'Builder');
        $builder = PluginVaultRootGroup::for('voodbuilder');
        $canonical = GalleryUploadTarget::resolve((int) $builder->getKey());

        $duplicate = MediaGallery::query()->create([
            'name' => 'Library',
            'slug' => 'library-1',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent_id' => $builder->getKey(),
            'parent_key' => (int) $builder->getKey(),
            'integration_source' => 'voodbuilder',
            'integration_key' => 'library',
            'is_public' => true,
            'sort_order' => 99,
        ]);

        $this->assertSame(1, DuplicateLibraryAlbumPruner::prune());

        $this->assertDatabaseMissing((new MediaGallery)->getTable(), [
            'id' => $duplicate->getKey(),
        ]);
        $this->assertSame(1, $builder->fresh()->children()->count());
        $this->assertSame(
            (int) $canonical->getKey(),
            (int) MediaGallery::query()
                ->where('parent_id', $builder->getKey())
                ->where('kind', MediaGallery::KIND_ALBUM)
                ->value('id'),
        );
    }
}
