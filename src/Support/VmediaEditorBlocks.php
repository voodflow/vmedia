<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Voodbuilder\GalleryFeaturedBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryGridBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryMarqueeBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryMasonryBlock;
use Voodflow\Voodbuilder\Voodbuilder;

/**
 * Registers VoodBuilder editorServerBlock entries for gallery presentations.
 */
final class VmediaEditorBlocks
{
    public static function register(): void
    {
        if (! class_exists(Voodbuilder::class)) {
            return;
        }

        $category = (string) __('vmedia::admin.editor.category');

        Voodbuilder::editorServerBlock($category, GalleryGridBlock::class);
        Voodbuilder::editorServerBlock($category, GalleryMasonryBlock::class);
        Voodbuilder::editorServerBlock($category, GalleryFeaturedBlock::class);
        Voodbuilder::editorServerBlock($category, GalleryMarqueeBlock::class);
    }
}
