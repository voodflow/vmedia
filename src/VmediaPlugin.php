<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\File;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;
use Voodflow\Vmedia\Filament\Resources\MediaItemResource;
use Voodflow\Vmedia\Filament\Resources\MediaTagResource;
use Voodflow\Vmedia\Filament\Widgets\MediaStatsWidget;

/**
 * Filament plugin: reusable media library & galleries (Spatie Media Library).
 *
 * Register alone for a standalone media admin, or next to a page builder
 * for editor Asset Manager integration.
 */
class VmediaPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'vmedia';
    }

    public function register(Panel $panel): void
    {
        if (! (bool) config('vmedia.enabled', true)) {
            return;
        }

        Vmedia::activate();

        $this->registerAssets();

        $panel
            ->resources([
                MediaGalleryResource::class,
                MediaItemResource::class,
                MediaTagResource::class,
            ])
            ->widgets([
                MediaStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    protected function registerAssets(): void
    {
        $assets = [
            'media-browser' => dirname(__DIR__).'/resources/css/media-browser.css',
            'vmedia-markdown-editor' => dirname(__DIR__).'/resources/css/markdown-editor.css',
            'vmedia-markdown-editor-setup' => dirname(__DIR__).'/resources/js/filament/vmedia-markdown-editor-setup.js',
        ];

        $registered = [];

        foreach ($assets as $id => $source) {
            if (! is_file($source)) {
                continue;
            }

            if ($id === 'media-browser') {
                $destination = public_path('css/vmedia/media-browser.css');
                File::ensureDirectoryExists(dirname($destination));

                if (! is_file($destination) || filemtime($source) > filemtime($destination)) {
                    File::copy($source, $destination);
                }
            }

            if (str_ends_with($source, '.css')) {
                $registered[] = Css::make($id, $source);

                continue;
            }

            $registered[] = Js::make($id, $source);
        }

        if ($registered === []) {
            return;
        }

        FilamentAsset::register($registered, 'voodflow/vmedia');
    }
}
