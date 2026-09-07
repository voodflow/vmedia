<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Media resource authorization for Filament + editor upload routes.
 *
 * Policies historically check Shield-style abilities (`ViewAny:MediaItem`). That
 * blocks uploads on a stock Filament install with no permission package. Driver:
 *
 * - `auto` (default): use named abilities when Filament Shield is present;
 *   otherwise allow Filament panel users.
 * - `permissions`: always require named abilities (Shield / custom Gate).
 * - `panel`: always allow Filament panel users (ignore named abilities).
 */
final class MediaAuthorization
{
    public static function allows(?Authenticatable $user, string $ability): bool
    {
        if ($user === null) {
            return false;
        }

        $driver = (string) config('vmedia.authorization.driver', 'auto');

        return match ($driver) {
            'panel' => self::userCanAccessPanel(),
            'permissions' => self::canAbility($user, $ability),
            default => self::auto($user, $ability),
        };
    }

    private static function auto(Authenticatable $user, string $ability): bool
    {
        if (self::usesPermissionAuthorizer()) {
            return self::canAbility($user, $ability);
        }

        return self::userCanAccessPanel();
    }

    private static function canAbility(Authenticatable $user, string $ability): bool
    {
        return method_exists($user, 'can')
            ? (bool) $user->can($ability)
            : false;
    }

    public static function usesPermissionAuthorizer(): bool
    {
        return class_exists(FilamentShieldPlugin::class);
    }

    public static function userCanAccessPanel(): bool
    {
        $user = auth()->user();

        if (! $user instanceof Authenticatable || ! class_exists(Filament::class)) {
            return false;
        }

        $panels = [];

        try {
            foreach (Filament::getPanels() as $panel) {
                if ($panel instanceof Panel) {
                    $panels[] = $panel;
                }
            }
        } catch (\Throwable) {
            //
        }

        if ($panels === []) {
            // Editor/media routes often run with no "current" Filament panel. An empty
            // registry here is usually boot timing — not "user cannot access admin".
            // Without Shield, authenticated users may manage media (stock Filament).
            return ! self::usesPermissionAuthorizer();
        }

        foreach ($panels as $panel) {
            if ($user instanceof FilamentUser) {
                if ($user->canAccessPanel($panel)) {
                    return true;
                }

                continue;
            }

            // No FilamentUser contract: Filament allows every panel.
            return true;
        }

        return false;
    }
}
