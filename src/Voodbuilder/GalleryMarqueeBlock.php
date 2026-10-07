<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Voodbuilder;

use Voodflow\Vmedia\Support\GalleryBlockSupport;

final class GalleryMarqueeBlock extends AbstractGalleryBlock
{
    public static function getId(): string
    {
        return 'vmedia_gallery_marquee';
    }

    public static function getLabel(): string
    {
        return (string) __('vmedia::admin.editor.blocks.marquee');
    }

    protected static function layout(): string
    {
        return GalleryBlockSupport::LAYOUT_MARQUEE;
    }
}
