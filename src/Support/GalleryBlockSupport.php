<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Shared config + slide resolution for VoodBuilder gallery presentation blocks.
 */
final class GalleryBlockSupport
{
    public const LAYOUT_GRID = 'grid';

    public const LAYOUT_MASONRY = 'masonry';

    public const LAYOUT_FEATURED = 'featured';

    public const LAYOUT_MARQUEE = 'marquee';

    /** @var list<string> */
    public const LAYOUTS = [
        self::LAYOUT_GRID,
        self::LAYOUT_MASONRY,
        self::LAYOUT_FEATURED,
        self::LAYOUT_MARQUEE,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(string $layout): array
    {
        return self::normalizeConfig([], $layout);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *     gallery_id: int|null,
     *     layout: string,
     *     heading: string|null,
     *     columns: int,
     *     limit: int,
     *     gap: string,
     *     aspect: string,
     *     show_captions: bool,
     *     lightbox: bool,
     *     speed: int,
     *     pause_on_hover: bool,
     *     direction: string,
     *     rounded: bool
     * }
     */
    public static function normalizeConfig(array $config, string $layout): array
    {
        $layout = in_array($layout, self::LAYOUTS, true) ? $layout : self::LAYOUT_GRID;

        $gap = (string) ($config['gap'] ?? 'md');

        if (! in_array($gap, ['sm', 'md', 'lg'], true)) {
            $gap = 'md';
        }

        $aspect = (string) ($config['aspect'] ?? ($layout === self::LAYOUT_MASONRY ? 'auto' : '4/3'));

        if (! in_array($aspect, ['auto', '1/1', '4/3', '16/9', '3/4'], true)) {
            $aspect = '4/3';
        }

        $direction = (string) ($config['direction'] ?? 'left');

        if (! in_array($direction, ['left', 'right'], true)) {
            $direction = 'left';
        }

        $galleryId = $config['gallery_id'] ?? null;
        $galleryId = is_numeric($galleryId) ? max(0, (int) $galleryId) : null;

        if ($galleryId === 0) {
            $galleryId = null;
        }

        $heading = filled($config['heading'] ?? null) ? trim((string) $config['heading']) : null;

        return [
            'gallery_id' => $galleryId,
            'layout' => $layout,
            'heading' => $heading,
            'columns' => max(1, min(6, (int) ($config['columns'] ?? ($layout === self::LAYOUT_FEATURED ? 4 : 3)))),
            'limit' => max(0, min(48, (int) ($config['limit'] ?? ($layout === self::LAYOUT_FEATURED ? 9 : 12)))),
            'gap' => $gap,
            'aspect' => $aspect,
            'show_captions' => (bool) ($config['show_captions'] ?? false),
            'lightbox' => (bool) ($config['lightbox'] ?? true),
            'speed' => max(8, min(120, (int) ($config['speed'] ?? 40))),
            'pause_on_hover' => (bool) ($config['pause_on_hover'] ?? true),
            'direction' => $direction,
            'rounded' => (bool) ($config['rounded'] ?? true),
        ];
    }

    public static function viewFor(string $layout): string
    {
        return match ($layout) {
            self::LAYOUT_MASONRY => 'vmedia::blocks.gallery-masonry',
            self::LAYOUT_FEATURED => 'vmedia::blocks.gallery-featured',
            self::LAYOUT_MARQUEE => 'vmedia::blocks.gallery-marquee',
            default => 'vmedia::blocks.gallery-grid',
        };
    }

    /**
     * Full Tailwind class names (string literals) so Grapes canvas + site JIT
     * pick them up. Custom <style> tags are stripped from the editor canvas.
     *
     * @param  array<string, mixed>  $config
     * @return array{
     *     gap: string,
     *     rounded: string,
     *     roundedImg: string,
     *     aspect: string,
     *     grid: string,
     *     masonry: string,
     *     featuredRow: string,
     *     thumb: string,
     *     image: string
     * }
     */
    public static function uiClasses(array $config): array
    {
        $config = self::normalizeConfig($config, (string) ($config['layout'] ?? self::LAYOUT_GRID));
        $gap = match ($config['gap']) {
            'sm' => 'gap-2',
            'lg' => 'gap-6',
            default => 'gap-4',
        };
        $rounded = $config['rounded'] ? 'rounded-xl' : 'rounded-none';
        $roundedImg = $config['rounded'] ? 'rounded-xl' : 'rounded-none';
        $aspect = match ($config['aspect']) {
            '1/1' => 'aspect-square',
            '16/9' => 'aspect-video',
            '3/4' => 'aspect-[3/4]',
            'auto' => 'aspect-auto h-auto',
            default => 'aspect-[4/3]',
        };
        $columns = (int) $config['columns'];
        $grid = match ($columns) {
            1 => 'grid grid-cols-1',
            2 => 'grid grid-cols-1 sm:grid-cols-2',
            4 => 'grid grid-cols-2 lg:grid-cols-4',
            5 => 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
            6 => 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
            default => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        };
        $masonry = match ($columns) {
            1 => 'columns-1',
            2 => 'columns-1 sm:columns-2',
            4 => 'columns-2 lg:columns-4',
            5 => 'columns-2 md:columns-3 lg:columns-5',
            6 => 'columns-2 md:columns-3 lg:columns-6',
            default => 'columns-1 sm:columns-2 lg:columns-3',
        };
        $featuredRow = match ($columns) {
            1 => 'grid grid-cols-1',
            2 => 'grid grid-cols-1 sm:grid-cols-2',
            3 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
            5 => 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
            6 => 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
            default => 'grid grid-cols-2 lg:grid-cols-4',
        };

        return [
            'gap' => $gap,
            'rounded' => $rounded,
            'roundedImg' => $roundedImg,
            'aspect' => $aspect,
            'grid' => trim("{$grid} {$gap}"),
            'masonry' => trim("{$masonry} {$gap}"),
            'featuredRow' => trim("{$featuredRow} {$gap}"),
            'thumb' => trim("block w-full overflow-hidden border-0 bg-transparent p-0 cursor-zoom-in {$rounded}"),
            'image' => trim("block w-full object-cover transition duration-300 hover:brightness-105 hover:scale-[1.02] {$aspect} {$roundedImg}"),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{url: string, thumb: string, alt: string, caption: string, type: string}>
     */
    public static function resolveSlides(array $config, bool $preview = false): array
    {
        $config = self::normalizeConfig($config, (string) ($config['layout'] ?? self::LAYOUT_GRID));
        $galleryId = $config['gallery_id'];

        if ($galleryId === null) {
            return $preview ? self::placeholderSlides($config['limit'] ?: 8) : [];
        }

        $gallery = MediaGallery::query()->find($galleryId);

        if ($gallery === null) {
            return $preview ? self::placeholderSlides($config['limit'] ?: 8) : [];
        }

        $assets = $gallery->isGroup()
            ? GalleryAggregate::paginateMedia($gallery, [
                'type' => 'image',
                'per_page' => $config['limit'] > 0 ? $config['limit'] : 48,
                'page' => 1,
            ])['data']
            : MediaLibrary::listAssets(type: 'image', galleryId: (int) $gallery->getKey());

        if ($config['limit'] > 0) {
            $assets = array_slice($assets, 0, $config['limit']);
        }

        $slides = [];

        foreach ($assets as $asset) {
            if (($asset['type'] ?? '') !== 'image') {
                continue;
            }

            $url = (string) ($asset['display'] ?? $asset['src'] ?? '');
            $thumb = (string) ($asset['thumb'] ?? $url);

            if ($url === '') {
                continue;
            }

            $slides[] = [
                'url' => $url,
                'thumb' => $thumb !== '' ? $thumb : $url,
                'alt' => (string) ($asset['alt'] ?? $asset['name'] ?? ''),
                'caption' => (string) ($asset['caption'] ?? ''),
                'type' => 'image',
            ];
        }

        if ($slides === [] && $preview) {
            return self::placeholderSlides($config['limit'] ?: 8);
        }

        return $slides;
    }

    /**
     * @return list<array{url: string, thumb: string, alt: string, caption: string, type: string}>
     */
    public static function placeholderSlides(int $count = 8): array
    {
        $count = max(4, min(12, $count));
        $palettes = [
            ['#0f172a', '#334155', 'Harbor light'],
            ['#1c1917', '#78716c', 'Stone terrace'],
            ['#042f2e', '#0d9488', 'Tide pool'],
            ['#1e1b4b', '#6366f1', 'Indigo dusk'],
            ['#431407', '#ea580c', 'Ember clay'],
            ['#14532d', '#22c55e', 'Meadow edge'],
            ['#4a044e', '#d946ef', 'Orchid glow'],
            ['#083344', '#06b6d4', 'Cyan bay'],
            ['#3f1d0b', '#f59e0b', 'Amber field'],
            ['#111827', '#9ca3af', 'Graphite grain'],
            ['#172554', '#3b82f6', 'Azure ridge'],
            ['#3b0764', '#a855f7', 'Violet mist'],
        ];

        $slides = [];

        for ($i = 0; $i < $count; $i++) {
            [$from, $to, $label] = $palettes[$i % count($palettes)];
            $svg = self::placeholderSvg($from, $to, $label, $i + 1);
            $slides[] = [
                'url' => $svg,
                'thumb' => $svg,
                'alt' => $label,
                'caption' => $label,
                'type' => 'image',
            ];
        }

        return $slides;
    }

    private static function placeholderSvg(string $from, string $to, string $label, int $index): string
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);
        $safeFrom = htmlspecialchars($from, ENT_QUOTES | ENT_XML1);
        $safeTo = htmlspecialchars($to, ENT_QUOTES | ENT_XML1);
        $height = 480 + (($index * 37) % 160);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="{$height}" viewBox="0 0 800 {$height}" role="img" aria-label="{$safeLabel}">
  <defs>
    <linearGradient id="g{$index}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$safeFrom}"/>
      <stop offset="100%" stop-color="{$safeTo}"/>
    </linearGradient>
  </defs>
  <rect width="800" height="{$height}" fill="url(#g{$index})"/>
  <circle cx="640" cy="120" r="90" fill="rgba(255,255,255,0.08)"/>
  <circle cx="120" cy="{$height}" r="140" fill="rgba(255,255,255,0.06)"/>
  <text x="48" y="{$height}" dy="-48" fill="rgba(255,255,255,0.88)" font-family="ui-sans-serif,system-ui,sans-serif" font-size="28" font-weight="600">{$safeLabel}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}
