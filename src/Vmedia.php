<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

/**
 * Runtime activation for the Filament media companion.
 *
 * Installing the Composer package alone is not enough: the host must register
 * VmediaPlugin on a Filament panel (unless auto_register is on).
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
        VmediaRoutes::register();
    }

    public static function reset(): void
    {
        self::$active = false;
        VmediaRoutes::reset();
    }

    public static function isActive(): bool
    {
        return self::$active && (bool) config('vmedia.enabled', true);
    }
}
