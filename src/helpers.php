<?php

declare(strict_types=1);

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\MediaLibrary;

if (! function_exists('vmedia_gallery')) {
    /**
     * Render a public gallery by slug or id.
     *
     * @param  string|int  $gallery  slug or id
     * @param  string|null  $view  blade view (defaults to package public gallery partial)
     */
    function vmedia_gallery(string|int $gallery, ?string $view = null): string
    {
        $model = is_numeric($gallery)
            ? MediaGallery::query()->find((int) $gallery)
            : MediaGallery::query()->where('slug', (string) $gallery)->first();

        if ($model === null || ! $model->is_public) {
            return '';
        }

        $assets = MediaLibrary::listAssets(galleryId: (int) $model->getKey());
        $viewName = $view ?? 'vmedia::public.gallery-embed';

        return view($viewName, [
            'gallery' => $model,
            'assets' => $assets,
        ])->render();
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
            '/\[vmedia-gallery\s+slug=(["\'])([^"\']+)\1\s*\]/i',
            static function (array $matches) use ($view): string {
                return vmedia_gallery($matches[2], $view);
            },
            $content,
        );
    }
}
