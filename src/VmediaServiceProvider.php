<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Voodflow\Vmedia\Console\InstallCommand;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;
use Voodflow\Vmedia\Policies\MediaGalleryPolicy;
use Voodflow\Vmedia\Policies\MediaItemPolicy;

class VmediaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'vmedia';

    public static string $viewNamespace = 'vmedia';

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
            if (! (bool) config('vmedia.auto_register', false)) {
                return;
            }

            Vmedia::activate();
        });
    }

    public function packageBooted(): void
    {
        // Morph aliases kept for backward compatibility with existing media rows.
        Relation::morphMap([
            'voodbuilder_media_gallery' => MediaGallery::class,
            'voodbuilder_media_vault' => MediaVault::class,
            'vmedia_gallery' => MediaGallery::class,
            'vmedia_vault' => MediaVault::class,
        ]);

        Gate::policy(MediaGallery::class, MediaGalleryPolicy::class);
        Gate::policy(MediaItem::class, MediaItemPolicy::class);
    }
}
