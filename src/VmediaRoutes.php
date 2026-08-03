<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Illuminate\Support\Facades\Route;
use Voodflow\Vmedia\Http\Controllers\MediaController;
use Voodflow\Vmedia\Http\Middleware\EnsureVmediaAuthorized;

/**
 * Package-owned media HTTP routes (galleries browser + vault upload).
 *
 * Always registers under the configured vmedia prefix. Optionally also
 * registers page-builder editor path aliases for transitional compatibility.
 */
final class VmediaRoutes
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
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
                Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
            });

        if ((bool) config('vmedia.integrations.voodbuilder.editor_routes', true)) {
            $compatPrefix = (string) config('vmedia.integrations.voodbuilder.prefix', 'voodbuilder/editor');
            $compatName = (string) config('vmedia.integrations.voodbuilder.name_prefix', 'voodbuilder.editor.');

            Route::middleware($middleware)
                ->prefix($compatPrefix)
                ->name($compatName)
                ->group(function (): void {
                    Route::get('media/galleries', [MediaController::class, 'galleries'])->name('media.galleries');
                    Route::get('media', [MediaController::class, 'index'])->name('media.index');
                    Route::post('upload', [MediaController::class, 'store'])->name('upload');
                });
        }
    }

    public static function reset(): void
    {
        self::$registered = false;
    }
}
