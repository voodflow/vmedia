<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Voodflow\Vmedia\Console\EnsurePluginVaultRootsCommand;
use Voodflow\Vmedia\Console\InstallCommand;
use Voodflow\Vmedia\Console\PruneOrphansCommand;
use Voodflow\Vmedia\Console\StatsCommand;
use Voodflow\Vmedia\Http\Controllers\PublicGalleryController;
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
            ->hasCommands([
                InstallCommand::class,
                StatsCommand::class,
                PruneOrphansCommand::class,
                EnsurePluginVaultRootsCommand::class,
            ]);
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

        config()->set('media-library.media_model', MediaItem::class);

        Blade::directive('vmediaGallery', function (string $expression): string {
            return "<?php echo \\vmedia_gallery({$expression}); ?>";
        });

        Blade::directive('renderWithVmediaGalleries', function (string $expression): string {
            return "<?php echo \\render_with_vmedia_galleries({$expression}); ?>";
        });

        $this->registerPublicRoutes();
    }

    protected function registerPublicRoutes(): void
    {
        if (! (bool) config('vmedia.public.enabled', true)) {
            return;
        }

        if (Route::has('vmedia.public.gallery')) {
            return;
        }

        $prefix = (string) config('vmedia.public.prefix', 'galleries');
        $middleware = (array) config('vmedia.public.middleware', ['web']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(function (): void {
                Route::get('{path}', PublicGalleryController::class)
                    ->where('path', '.*')
                    ->name('vmedia.public.gallery');
            });
    }
}
