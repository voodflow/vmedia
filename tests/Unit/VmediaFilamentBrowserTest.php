<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Support\GalleryBrowser;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Tests\OrchestraTestCase;

class VmediaFilamentBrowserTest extends OrchestraTestCase
{
    public function test_paginate_for_picker_returns_data_not_assets_key(): void
    {
        $result = GalleryBrowser::paginateForPicker(
            type: 'image',
            parentFolderId: null,
            galleryId: null,
            search: null,
            page: 1,
            perPage: 20,
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayNotHasKey('assets', $result);

        $assets = array_map(
            static fn (array $asset): array => MediaLibrary::toBrowserTile($asset),
            $result['data'],
        );

        $this->assertIsArray($assets);
    }
}
