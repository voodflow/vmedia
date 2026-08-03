<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;
use Voodflow\Vmedia\Filament\Resources\MediaItemResource;

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

        $panel->resources([
            MediaGalleryResource::class,
            MediaItemResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
