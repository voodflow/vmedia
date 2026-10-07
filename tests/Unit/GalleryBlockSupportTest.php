<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Voodflow\Vmedia\Support\GalleryBlockSupport;
use Voodflow\Vmedia\Tests\OrchestraTestCase;
use Voodflow\Vmedia\Voodbuilder\GalleryFeaturedBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryGridBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryMarqueeBlock;
use Voodflow\Vmedia\Voodbuilder\GalleryMasonryBlock;

final class GalleryBlockSupportTest extends OrchestraTestCase
{
    #[Test]
    public function it_normalizes_grid_defaults(): void
    {
        $config = GalleryBlockSupport::normalizeConfig([], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertNull($config['gallery_id']);
        $this->assertSame('grid', $config['layout']);
        $this->assertSame(3, $config['columns']);
        $this->assertTrue($config['lightbox']);
        $this->assertSame('4/3', $config['aspect']);
    }

    #[Test]
    public function it_clamps_marquee_speed_and_columns(): void
    {
        $config = GalleryBlockSupport::normalizeConfig([
            'speed' => 3,
            'columns' => 99,
            'direction' => 'up',
            'gap' => 'huge',
        ], GalleryBlockSupport::LAYOUT_MARQUEE);

        $this->assertSame(8, $config['speed']);
        $this->assertSame(6, $config['columns']);
        $this->assertSame('left', $config['direction']);
        $this->assertSame('md', $config['gap']);
    }

    #[Test]
    public function it_returns_placeholder_slides_in_preview_without_gallery(): void
    {
        $slides = GalleryBlockSupport::resolveSlides([
            'layout' => GalleryBlockSupport::LAYOUT_GRID,
            'limit' => 6,
        ], preview: true);

        $this->assertCount(6, $slides);
        $this->assertStringStartsWith('data:image/svg+xml', $slides[0]['url']);
    }

    #[Test]
    public function it_returns_empty_slides_on_live_without_gallery(): void
    {
        $slides = GalleryBlockSupport::resolveSlides([
            'layout' => GalleryBlockSupport::LAYOUT_GRID,
        ], preview: false);

        $this->assertSame([], $slides);
    }

    #[Test]
    public function gallery_blocks_render_preview_html(): void
    {
        foreach ([
            GalleryGridBlock::class,
            GalleryMasonryBlock::class,
            GalleryFeaturedBlock::class,
            GalleryMarqueeBlock::class,
        ] as $blockClass) {
            $html = $blockClass::toPreviewHtml($blockClass::defaultConfig(), []);

            $this->assertStringContainsString($blockClass::getId(), $html);
        }
    }

    #[Test]
    public function ui_classes_expose_tailwind_gap_and_rounded(): void
    {
        $ui = GalleryBlockSupport::uiClasses([
            'layout' => GalleryBlockSupport::LAYOUT_GRID,
            'gap' => 'lg',
            'rounded' => true,
            'columns' => 3,
            'aspect' => '4/3',
        ]);

        $this->assertStringContainsString('gap-6', $ui['grid']);
        $this->assertStringContainsString('lg:grid-cols-3', $ui['grid']);
        $this->assertStringContainsString('rounded-xl', $ui['rounded']);
        $this->assertStringContainsString('aspect-[4/3]', $ui['aspect']);
    }

    #[Test]
    public function it_normalizes_caption_position(): void
    {
        $above = GalleryBlockSupport::normalizeConfig([
            'caption_position' => 'above',
        ], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertSame('above', $above['caption_position']);

        $fallback = GalleryBlockSupport::normalizeConfig([
            'caption_position' => 'side',
        ], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertSame('below', $fallback['caption_position']);
    }
}
