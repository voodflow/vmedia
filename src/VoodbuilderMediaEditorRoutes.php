<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia;

use Illuminate\Support\Facades\Route;
use Voodflow\VoodbuilderMedia\Http\Controllers\EditorMediaController;

/**
 * Editor media routes owned by this companion (galleries browser + vault upload).
 */
final class VoodbuilderMediaEditorRoutes
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        if (! (bool) config('voodbuilder-media.enabled', true)) {
            return;
        }

        if (! (bool) config('voodbuilder-media.voodbuilder.editor_routes', true)) {
            return;
        }

        if (! class_exists(\Voodflow\Voodbuilder\Voodbuilder::class)) {
            return;
        }

        self::$registered = true;

        Route::middleware(['web', 'auth', 'throttle:60,1'])
            ->prefix('voodbuilder/editor')
            ->name('voodbuilder.editor.')
            ->group(function (): void {
                Route::get('media/galleries', [EditorMediaController::class, 'galleries'])->name('media.galleries');
                Route::get('media', [EditorMediaController::class, 'index'])->name('media.index');
                Route::post('upload', [EditorMediaController::class, 'store'])->name('upload');
            });
    }
}
