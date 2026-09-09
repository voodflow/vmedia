<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Closure;
use Voodflow\Vmedia\Support\Integration\PluginVaultRegistry;
use Voodflow\Vmedia\Support\Integration\PluginVaultRootBootstrap;

/**
 * Runtime activation for the Filament media companion.
 *
 * Installing the Composer package alone is not enough: the host must register
 * VmediaPlugin on a Filament panel (unless auto_register is on).
 *
 * Companion packages register their own vault roots via {@see registerPluginVault()}.
 */
final class Vmedia
{
    private static bool $active = false;

    public static function activate(): void
    {
        if (self::$active) {
            return;
        }

        self::$active = true;
        self::registerRoutesOutsideFilamentGroup();
    }

    public static function reset(): void
    {
        self::$active = false;
        VmediaRoutes::reset();
        PluginVaultRegistry::reset();
    }

    public static function isActive(): bool
    {
        return self::$active && (bool) config('vmedia.enabled', true);
    }

    /**
     * Declare a top-level vault folder owned by a companion plugin (Builder, Vtuts, …).
     * Call from the companion ServiceProvider `register()` / `boot()`, then {@see ensurePluginVault()}.
     */
    public static function registerPluginVault(
        string $source,
        string $slug,
        string|Closure $name,
        ?string $integrationKey = null,
    ): void {
        PluginVaultRegistry::register($source, $slug, $name, $integrationKey);
    }

    /**
     * Create/sync the registered vault root and its Library album (idempotent).
     */
    public static function ensurePluginVault(string $source): void
    {
        PluginVaultRootBootstrap::ensureFor($source);
    }

    /**
     * Filament resolves panels while loading routes under Route::name('filament.').
     * Plugin::register() therefore runs inside that group — defer HTTP routes so
     * names stay `vmedia.*` instead of `filament.vmedia.*`.
     */
    private static function registerRoutesOutsideFilamentGroup(): void
    {
        $register = static function (): void {
            VmediaRoutes::register();
        };

        if (app()->isBooted()) {
            $register();

            return;
        }

        app()->booted($register);
    }
}
