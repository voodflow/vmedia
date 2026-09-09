<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Illuminate\Support\Facades\Route;
use Voodflow\Vmedia\Http\Controllers\MediaController;
use Voodflow\Vmedia\Http\Middleware\EnsureVmediaAuthorized;

/**
 * Package-owned media HTTP routes (galleries browser + vault upload).
 */
final class VmediaRoutes
{
    private static bool $registered = false;

    public static function register(): void
    {
        // Application refresh (e.g. Testbench) clears routes but static flags may linger.
        // Also re-register when a prior call nested under Filament's `filament.` name group.
        if (self::$registered && Route::has('vmedia.media.index')) {
            return;
        }

        if (! (bool) config('vmedia.enabled', true)) {
            return;
        }

        self::$registered = true;

        $middleware = array_values(array_filter([
            ...(array) config('vmedia.routes.middleware', ['web', 'auth', 'throttle:60,1']),
            EnsureVmediaAuthorized::class,
        ]));

        $prefix = (string) config('vmedia.routes.prefix', 'vmedia');
        $namePrefix = (string) config('vmedia.routes.name_prefix', 'vmedia.');

        Route::middleware($middleware)
            ->prefix($prefix)
            ->name($namePrefix)
            ->group(function (): void {
                Route::get('media/galleries', [MediaController::class, 'galleries'])->name('media.galleries');
                Route::get('media', [MediaController::class, 'index'])->name('media.index');
                Route::post('media/upload', [MediaController::class, 'store'])->name('media.upload');
                Route::post('media/replace', [MediaController::class, 'replace'])->name('media.replace');
                Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
            });
    }

    public static function reset(): void
    {
        self::$registered = false;
    }
}
