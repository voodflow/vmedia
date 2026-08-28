<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryPath;
use Voodflow\Vmedia\Support\GalleryUploadTarget;
use Voodflow\Vmedia\Support\Integration\PluginVaultLibraryGallery;
use Voodflow\Vmedia\Support\Integration\PluginVaultRootGroup;
use Voodflow\Vmedia\Tests\TestCase;

class GalleryUploadTargetTest extends TestCase
{
    public function test_resolves_plugin_group_to_library_album(): void
    {
        $builderRoot = PluginVaultRootGroup::voodbuilder();
        $library = PluginVaultLibraryGallery::album('voodbuilder');

        $resolved = GalleryUploadTarget::resolve((int) $builderRoot->getKey());

        $this->assertSame((int) $library->getKey(), (int) $resolved->getKey());
        $this->assertTrue($resolved->isAlbum());
    }

    public function test_resolves_album_directly(): void
    {
        $library = PluginVaultLibraryGallery::album('vtuts');

        $resolved = GalleryUploadTarget::resolve((int) $library->getKey());

        $this->assertSame((int) $library->getKey(), (int) $resolved->getKey());
    }

    public function test_plugin_vault_library_matches_group_resolve(): void
    {
        $library = PluginVaultLibraryGallery::album('voodbuilder');
        $resolved = GalleryUploadTarget::resolve((int) PluginVaultRootGroup::voodbuilder()->getKey());

        $this->assertSame((int) $library->getKey(), (int) $resolved->getKey());
        $this->assertStringStartsWith('voodbuilder/library', GalleryPath::toPath($library));
    }

    public function test_payload_includes_path(): void
    {
        $library = PluginVaultLibraryGallery::album('vdocs');

        $payload = GalleryUploadTarget::payload(null, (int) $library->getKey());

        $this->assertSame((int) $library->getKey(), $payload['id']);
        $this->assertSame(MediaGallery::KIND_ALBUM, $payload['kind']);
        $this->assertNotSame('', $payload['path']);
    }
}
