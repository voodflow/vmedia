<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia;

/**
 * Runtime activation flag for the media companion (mirrors Elements/Popups style).
 */
final class VoodbuilderMedia
{
    protected static bool $active = false;

    public static function activate(): void
    {
        self::$active = true;
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
