<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia;

use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Voodflow\VoodbuilderMedia\Console\InstallCommand;
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

    public function packageRegistered(): void
    {
        $this->app->booting(function (): void {
            if (! (bool) config('voodbuilder-media.auto_register', false)) {
                return;
            }

            VoodbuilderMedia::activate();
        });
    }

    public function packageBooted(): void
    {
        Relation::morphMap([
            'voodbuilder_media_gallery' => MediaGallery::class,
            'voodbuilder_media_vault' => MediaVault::class,
        ]);

        // Routes are registered only when activated (Filament plugin or auto_register).
        // Mirrors Elements: commenting out the plugin restores Core media behaviour.
    }
}
