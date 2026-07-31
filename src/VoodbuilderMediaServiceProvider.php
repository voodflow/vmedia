<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Voodflow\VoodbuilderMedia\Console\InstallCommand;
use Voodflow\VoodbuilderMedia\Http\Controllers\EditorMediaController;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;
use Voodflow\VoodbuilderMedia\Models\MediaVault;

class VoodbuilderMediaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'voodbuilder-media';

    public static string $viewNamespace = 'voodbuilder-media';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews(static::$viewNamespace)
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommand(InstallCommand::class);
    }

    public function packageBooted(): void
    {
        Relation::morphMap([
            'voodbuilder_media_gallery' => MediaGallery::class,
            'voodbuilder_media_vault' => MediaVault::class,
        ]);

        if (
            (bool) config('voodbuilder-media.enabled', true)
            && (bool) config('voodbuilder-media.voodbuilder.editor_routes', true)
            && class_exists(\Voodflow\Voodbuilder\Voodbuilder::class)
        ) {
            $this->registerVoodbuilderEditorRoutes();
        }
    }

    protected function registerVoodbuilderEditorRoutes(): void
    {
        Route::middleware(['web', 'auth', 'throttle:60,1'])
            ->prefix('voodbuilder/editor')
            ->name('voodbuilder.editor.')
            ->group(function (): void {
                // Override Core media index/upload when this companion is installed.
                Route::get('media/galleries', [EditorMediaController::class, 'galleries'])->name('media.galleries');
                Route::get('media', [EditorMediaController::class, 'index'])->name('media.index');
                Route::post('upload', [EditorMediaController::class, 'store'])->name('upload');
            });
    }
}
