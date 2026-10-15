<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Voodbuilder\GalleryFeaturedBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryGridBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryMarqueeBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryMasonryBlock;
use Voodflow\Vmedia\Voodbuilder\MediaExplorerBlock;
use Voodflow\Voodbuilder\Voodbuilder;

/**
 * Registers VoodBuilder editorServerBlock entries for media presentations.
 *
 * Everything behind this class is a soft dependency on `voodflow/voodbuilder`:
 * VoodMedia is required by apps that have no page builder, so nothing here — not
 * even the class name of a block — may be touched unless VoodBuilder is installed.
 * The block classes implement VoodBuilder contracts and would fatal on load without
 * it, which is why the guard lives before every reference to them.
 *
 * The Livewire component behind a block is *not* registered here: that lives in the
 * service provider, so the component also works in apps without a page builder.
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
        Voodbuilder::editorServerBlock($category, MediaExplorerBlock::class);
    }
}
