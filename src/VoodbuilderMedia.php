<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia;

/**
 * Runtime activation for the Filament media companion.
 *
 * Installing the Composer package alone is not enough: the host must register
 * VoodbuilderMediaPlugin on a Filament panel (unless auto_register is on).
 */
final class VoodbuilderMedia
{
    private static bool $active = false;

    public static function activate(): void
    {
        if (self::$active) {
            return;
        }

        self::$active = true;
        VoodbuilderMediaEditorRoutes::register();
    }

    public static function reset(): void
    {
        self::$active = false;
    }

    public static function isActive(): bool
    {
        return self::$active && (bool) config('voodbuilder-media.enabled', true);
    }
}
