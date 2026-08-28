<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\Integration\PluginVaultLibraryGallery;
use Voodflow\Vmedia\Support\Integration\PluginVaultRootBootstrap;
use Voodflow\Vmedia\Support\Integration\PluginVaultRootGroup;
use Voodflow\Vmedia\Support\Integration\SharedLogosGallery;
use Voodflow\Vmedia\Tests\TestCase;

class PluginVaultRootGroupTest extends TestCase
{
    public function test_each_plugin_gets_a_top_level_root_folder(): void
    {
        PluginVaultRootBootstrap::ensureAll();

        $this->assertRoot('vexhibitors', 'root:exhibitors', 'exhibitors');
        $this->assertRoot('vevents', 'root:events', 'events');
        $this->assertRoot('vsponsors', 'root:sponsors', 'sponsors');
        $this->assertRoot('vpartners', 'root:partners', 'partners');
        $this->assertRoot('vtuts', 'root:vtuts', 'vtuts');
        $this->assertRoot('vdocs', 'root:vdocs', 'vdocs');
        $this->assertRoot('voodbuilder', 'root:voodbuilder', 'voodbuilder');
        $this->assertRoot('vforms', 'root:vforms', 'vforms');
    }

    public function test_logos_folder_is_shared_and_idempotent(): void
    {
        $first = PluginVaultRootGroup::logos();
        $second = PluginVaultRootGroup::logos();

        $this->assertSame((int) $first->getKey(), (int) $second->getKey());
        $this->assertSame('vmedia', $first->integration_source);
        $this->assertSame('root:logos', $first->integration_key);
        $this->assertTrue($first->isGroup());
    }

    public function test_shared_logos_album_is_reused(): void
    {
        $first = SharedLogosGallery::album();
        $second = SharedLogosGallery::album();

        $this->assertSame((int) $first->getKey(), (int) $second->getKey());
        $this->assertTrue($first->isAlbum());
        $this->assertSame(
            (int) PluginVaultRootGroup::logos()->getKey(),
            (int) $first->parent_id,
        );
    }

    public function test_resolve_parent_or_plugin_root_falls_back_to_plugin_folder(): void
    {
        $eventsRoot = PluginVaultRootGroup::events();

        $resolved = PluginVaultRootGroup::resolveParentOrPluginRoot(
            null,
            fn (): MediaGallery => PluginVaultRootGroup::events(),
        );

        $this->assertNotNull($resolved);
        $this->assertSame((int) $eventsRoot->getKey(), (int) $resolved->getKey());
    }

    public function test_plugin_library_album_lives_under_plugin_root(): void
    {
        $album = PluginVaultLibraryGallery::album('vtuts');

        $this->assertTrue($album->isAlbum());
        $this->assertSame(
            (int) PluginVaultRootGroup::vtuts()->getKey(),
            (int) $album->parent_id,
        );
    }

    protected function assertRoot(string $source, string $key, string $slug): void
    {
        $gallery = MediaGallery::query()
            ->where('integration_source', $source)
            ->where('integration_key', $key)
            ->first();

        $this->assertInstanceOf(MediaGallery::class, $gallery);
        $this->assertSame($slug, $gallery->slug);
        $this->assertTrue($gallery->isGroup());
    }
}
