<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Voodbuilder;

use Voodflow\Vmedia\Support\GalleryBlockSupport;

final class GalleryGridBlock extends AbstractGalleryBlock
{
    public static function getId(): string
    {
        return 'vmedia_gallery_grid';
    }

    public static function getLabel(): string
    {
        return (string) __('vmedia::admin.editor.blocks.grid');
    }

    protected static function layout(): string
    {
        return GalleryBlockSupport::LAYOUT_GRID;
    }
}
