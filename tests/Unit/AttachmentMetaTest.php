<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\AttachmentMeta;
use Voodflow\Vmedia\Tests\OrchestraTestCase;

class AttachmentMetaTest extends OrchestraTestCase
{
    public function test_markdown_alt_falls_back_to_display_title(): void
    {
        $media = new MediaItem([
            'name' => 'Product shot',
            'file_name' => 'product-shot.jpg',
        ]);

        $this->assertSame('Product shot', AttachmentMeta::markdownAlt($media));
    }

    public function test_markdown_alt_uses_custom_alt_when_set(): void
    {
        $media = new MediaItem([
            'name' => 'Product shot',
            'file_name' => 'product-shot.jpg',
            'custom_properties' => [
                MediaItem::CUSTOM_ALT => 'Hero banner',
            ],
        ]);

        $this->assertSame('Hero banner', AttachmentMeta::markdownAlt($media));
    }
}
