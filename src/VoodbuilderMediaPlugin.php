<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaItemResource;

/**
 * Filament plugin: reusable media library & galleries (Spatie Media Library).
 *
 * Register alone for a standalone media admin, or next to VoodbuilderPlugin
 * for editor Asset Manager integration.
 */
class VoodbuilderMediaPlugin implements Plugin
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
        return 'voodbuilder-media';
    }

    public function register(Panel $panel): void
    {
        if (! (bool) config('voodbuilder-media.enabled', true)) {
            return;
        }

        VoodbuilderMedia::activate();

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
