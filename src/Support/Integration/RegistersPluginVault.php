<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Closure;
use Voodflow\Vmedia\Vmedia;

/**
 * Shared boot helper for companion packages that own a VoodMedia vault root.
 */
final class RegistersPluginVault
{
    public static function register(
        string $source,
        string $slug,
        string|Closure $name,
        ?string $integrationKey = null,
    ): void {
        if (! class_exists(Vmedia::class)) {
            return;
        }

        Vmedia::registerPluginVault($source, $slug, $name, $integrationKey);
    }

    /**
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public static function ensureOnBoot(mixed $app, string $source): void
    {
        if (! class_exists(Vmedia::class)) {
            return;
        }

        $app->booted(static function () use ($source): void {
            Vmedia::ensurePluginVault($source);
        });
    }
}
