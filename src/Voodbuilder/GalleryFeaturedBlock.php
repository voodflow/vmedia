<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Voodbuilder;

use Voodflow\Vmedia\Support\GalleryBlockSupport;

final class GalleryFeaturedBlock extends AbstractGalleryBlock
{
    public static function getId(): string
    {
        return 'vmedia_gallery_featured';
    }

    public static function getLabel(): string
    {
        return (string) __('vmedia::admin.editor.blocks.featured');
    }

    protected static function layout(): string
    {
        return GalleryBlockSupport::LAYOUT_FEATURED;
    }
}
