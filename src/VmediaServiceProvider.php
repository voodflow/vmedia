<?php

declare(strict_types=1);

namespace Voodflow\Vmedia;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Voodflow\Vmedia\Console\EnsurePluginVaultRootsCommand;
use Voodflow\Vmedia\Console\InstallCommand;
use Voodflow\Vmedia\Console\PruneOrphansCommand;
use Voodflow\Vmedia\Console\RegenerateConversionsCommand;
use Voodflow\Vmedia\Console\StatsCommand;
use Voodflow\Vmedia\Http\Controllers\PublicGalleryController;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;
use Voodflow\Vmedia\Policies\MediaGalleryPolicy;
use Voodflow\Vmedia\Policies\MediaItemPolicy;
use Voodflow\Vmedia\Support\VmediaEditorBlocks;
use Voodflow\Vmedia\Support\VmediaEditorBridge;
use Voodflow\Voodbuilder\Voodbuilder;

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
                RegenerateConversionsCommand::class,
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
        // Canonical morph aliases for Spatie media ownership.
        Relation::morphMap([
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

        if (class_exists(Voodbuilder::class)) {
            Voodbuilder::reservePathPrefix(
                (string) config('vmedia.routes.prefix', 'vmedia'),
                (string) config('vmedia.public.prefix', 'galleries'),
            );

            VmediaEditorBlocks::register();
            VmediaEditorBridge::register();
            $this->publishes([
                __DIR__ . '/../resources/js/editor/plugin.iife.js' => public_path('vendor/vmedia/editor-plugin.js'),
            ], 'vmedia-assets');
            $this->ensureAssetPublished(
                __DIR__ . '/../resources/js/editor/plugin.iife.js',
                public_path('vendor/vmedia/editor-plugin.js'),
            );
            $this->registerEditorPluginScript();
        }
    }

    private function ensureAssetPublished(string $source, string $target): void
    {
        if (! is_file($source)) {
            return;
        }

        $dir = dirname($target);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (! is_file($target) || filemtime($source) > filemtime($target)) {
            @copy($source, $target);
        }
    }

    private function registerEditorPluginScript(): void
    {
        View::composer([
            'voodbuilder::pages.site-page',
            'voodbuilder::pages.chrome-layout-editor',
        ], function ($view): void {
            $data = $view->getData();
            $editing = (bool) ($data['editorEditor'] ?? false)
                || (bool) ($data['chromeLayoutEditor'] ?? false);

            if (! $editing) {
                return;
            }

            $public = public_path('vendor/vmedia/editor-plugin.js');
            $source = __DIR__ . '/../resources/js/editor/plugin.iife.js';
            $path = is_file($public) ? $public : $source;

            if (! is_file($path)) {
                return;
            }

            $href = is_file($public)
                ? asset('vendor/vmedia/editor-plugin.js') . '?v=' . filemtime($public)
                : 'data:application/javascript;base64,' . base64_encode((string) file_get_contents($source));

            $bridge = [
                'galleries' => VmediaEditorBridge::galleryOptions(),
            ];

            $view->getFactory()->startPush('scripts');
            echo '<script data-vmedia-editor-bridge>window.__voodbuilderVmedia='
                . json_encode($bridge, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
                . ';</script>';
            echo '<script src="' . e($href) . '" defer data-vmedia-editor-plugin></script>';
            $view->getFactory()->stopPush();
        });
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
