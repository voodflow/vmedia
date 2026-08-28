<?php

declare(strict_types=1);

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryAggregate;
use Voodflow\Vmedia\Support\GalleryPath;
use Voodflow\Vmedia\Support\MediaLibrary;

if (! function_exists('vmedia_gallery')) {
    /**
     * Render a public gallery by path, slug or id.
     *
     * @param  string|int  $gallery  path (2024/queen), slug or id
     * @param  string|null  $view  blade view (defaults to package public gallery partial)
     * @param  array<string, mixed>  $options  aggregate options when rendering a group
     */
    function vmedia_gallery(string|int $gallery, ?string $view = null, array $options = []): string
    {
        $model = vmedia_resolve_gallery($gallery);

        if ($model === null || ! $model->is_public) {
            return '';
        }

        if ($model->isGroup()) {
            $mode = (string) ($options['mode'] ?? 'media');
            $viewName = $view ?? ($mode === 'children'
                ? 'vmedia::public.group-children'
                : 'vmedia::public.group-media');

            if ($mode === 'children') {
                return view($viewName, [
                    'gallery' => $model,
                    'children' => GalleryAggregate::childAlbumPayloads($model),
                    'path' => GalleryPath::toPath($model),
                ])->render();
            }

            $result = GalleryAggregate::paginateMedia($model, $options);

            return view($viewName, [
                'gallery' => $model,
                'assets' => $result['data'],
                'meta' => $result['meta'],
                'path' => GalleryPath::toPath($model),
            ])->render();
        }

        $assets = MediaLibrary::listAssets(galleryId: (int) $model->getKey());
        $viewName = $view ?? 'vmedia::public.gallery-embed';

        return view($viewName, [
            'gallery' => $model,
            'assets' => $assets,
            'path' => GalleryPath::toPath($model),
        ])->render();
    }
}

if (! function_exists('vmedia_resolve_gallery')) {
    function vmedia_resolve_gallery(string|int $gallery): ?MediaGallery
    {
        if (is_numeric($gallery)) {
            return MediaGallery::query()->find((int) $gallery);
        }

        $path = trim((string) $gallery, '/');

        if (str_contains($path, '/')) {
            return GalleryPath::resolve($path);
        }

        return MediaGallery::query()->where('slug', $path)->first();
    }
}

if (! function_exists('vmedia_aggregate_gallery')) {
    /**
     * Paginated media from a group and all descendant albums.
     *
     * @param  string|int|MediaGallery  $gallery
     * @param  array<string, mixed>  $options
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>, gallery: MediaGallery|null, album_ids: list<int>}
     */
    function vmedia_aggregate_gallery(string|int|MediaGallery $gallery, array $options = []): array
    {
        $model = $gallery instanceof MediaGallery
            ? $gallery
            : vmedia_resolve_gallery($gallery);

        if ($model === null || ! $model->isGroup()) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) ($options['per_page'] ?? 48),
                    'total' => 0,
                    'has_more' => false,
                ],
                'gallery' => $model,
                'album_ids' => [],
            ];
        }

        return GalleryAggregate::paginateMedia($model, $options);
    }
}

if (! function_exists('vmedia_gallery_shortcode')) {
    /**
     * Shortcode for TipTap / rich text: [vmedia-gallery slug="my-gallery"]
     */
    function vmedia_gallery_shortcode(string $slug): string
    {
        return '[vmedia-gallery slug="'.addslashes($slug).'"]';
    }
}

if (! function_exists('render_with_vmedia_galleries')) {
    /**
     * Replace [vmedia-gallery slug="…"] shortcodes inside HTML/content.
     */
    function render_with_vmedia_galleries(string $content, ?string $view = null): string
    {
        return (string) preg_replace_callback(
            '/\[vmedia-gallery\s+slug=(["\'])([^"\']+)\1(?:\s+mode=(["\'])([^"\']+)\3)?\s*\]/i',
            static function (array $matches) use ($view): string {
                $options = [];

                if (($matches[4] ?? '') !== '') {
                    $options['mode'] = $matches[4];
                }

                return vmedia_gallery($matches[2], $view, $options);
            },
            $content,
        );
    }
}
