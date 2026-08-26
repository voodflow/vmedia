<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\File;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;
use Voodflow\Vmedia\Filament\Resources\MediaItemResource;
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
        $source = dirname(__DIR__).'/resources/css/media-browser.css';

        if (! is_file($source)) {
            return;
        }

        $destination = public_path('css/vmedia/media-browser.css');
        File::ensureDirectoryExists(dirname($destination));

        if (! is_file($destination) || filemtime($source) > filemtime($destination)) {
            File::copy($source, $destination);
        }

        FilamentAsset::register([
            Css::make('media-browser', $source),
        ], 'voodflow/vmedia');
    }
}
