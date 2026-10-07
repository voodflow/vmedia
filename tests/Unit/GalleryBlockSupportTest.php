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

            // Block id is stamped by EditorRichContentBlockAdapter::wrap (not Blade),
            // so preview HTML must expose the gallery root class instead.
            $this->assertStringContainsString('vmedia-gallery-block', $html);
        }
    }

    #[Test]
    public function ui_classes_expose_column_css_hook_and_gap(): void
    {
        $ui = GalleryBlockSupport::uiClasses([
            'layout' => GalleryBlockSupport::LAYOUT_GRID,
            'gap' => 'lg',
            'rounded' => true,
            'columns' => 3,
            'aspect' => '4/3',
        ]);

        $this->assertStringContainsString('gap-6', $ui['grid']);
        $this->assertStringContainsString('vmedia-gallery-cols', $ui['grid']);
        $this->assertSame(3, $ui['columns']);
        $this->assertStringContainsString('rounded-xl', $ui['rounded']);
        $this->assertStringContainsString('aspect-[4/3]', $ui['aspect']);
    }

    #[Test]
    public function it_normalizes_caption_position_and_bg(): void
    {
        $above = GalleryBlockSupport::normalizeConfig([
            'caption_position' => 'above',
        ], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertSame('above', $above['caption_position']);
        $this->assertSame('black', $above['caption_bg_color']);
        $this->assertSame(82, $above['caption_bg_opacity']);
        $this->assertSame('rgba(0, 0, 0, 0.82)', $above['caption_bg']);

        $overlay = GalleryBlockSupport::normalizeConfig([
            'caption_position' => 'overlay',
            'caption_bg_color' => 'zinc-900',
            'caption_bg_opacity' => 60,
        ], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertSame('overlay', $overlay['caption_position']);
        $this->assertSame('zinc-900', $overlay['caption_bg_color']);
        $this->assertSame(60, $overlay['caption_bg_opacity']);
        $this->assertSame('rgba(24, 24, 27, 0.6)', $overlay['caption_bg']);

        $legacy = GalleryBlockSupport::normalizeConfig([
            'caption_position' => 'side',
            'caption_bg' => 'rgba(0, 0, 0, 0.5)',
        ], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertSame('below', $legacy['caption_position']);
        $this->assertSame('black', $legacy['caption_bg_color']);
        $this->assertSame(50, $legacy['caption_bg_opacity']);

        $theme = GalleryBlockSupport::normalizeConfig([
            'caption_bg_color' => 'vp-brand-1',
            'caption_bg_opacity' => 70,
        ], GalleryBlockSupport::LAYOUT_GRID);

        $this->assertSame('vp-brand-1', $theme['caption_bg_color']);
        $this->assertSame(70, $theme['caption_bg_opacity']);
        $this->assertSame(
            'color-mix(in srgb, var(--color-vp-brand-1) 70%, transparent)',
            $theme['caption_bg'],
        );
    }
}
