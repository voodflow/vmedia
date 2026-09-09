<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Voodflow\Vmedia\Support\GalleryIntegrationSchema;

/**
 * Idempotent provisioning for registered plugin vault roots (+ shared Logos).
 */
final class PluginVaultRootBootstrap
{
    /** @var array<string, true> */
    private static array $deferredSources = [];

    private static bool $migrateListenerRegistered = false;

    private static bool $deferLogos = false;

    public static function ensureFor(string $integrationSource): void
    {
        if (self::shouldDeferUntilMigrateEnds()) {
            self::$deferredSources[$integrationSource] = true;
            self::registerMigrateEndedListener();

            return;
        }

        if (! self::canProvision()) {
            return;
        }

        self::provisionFor($integrationSource);
    }

    public static function ensureAll(): void
    {
        $sources = PluginVaultRegistry::sources();

        if (self::shouldDeferUntilMigrateEnds()) {
            foreach ($sources as $source) {
                self::$deferredSources[$source] = true;
            }
            self::$deferLogos = true;
            self::registerMigrateEndedListener();

            return;
        }

        if (! self::canProvision()) {
            return;
        }

        foreach ($sources as $source) {
            self::provisionFor($source);
        }

        PluginVaultRootGroup::logos();
        SharedLogosGallery::album();
    }

    public static function canProvision(): bool
    {
        return GalleryIntegrationSchema::isReady();
    }

    protected static function provisionFor(string $integrationSource): void
    {
        if ($integrationSource === 'vmedia' || $integrationSource === 'logos') {
            PluginVaultRootGroup::logos();
            SharedLogosGallery::album();

            return;
        }

        if (! PluginVaultRegistry::has($integrationSource)) {
            return;
        }

        PluginVaultRootGroup::for($integrationSource);
        PluginVaultLibraryGallery::album($integrationSource);
    }

    protected static function shouldDeferUntilMigrateEnds(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        if (! self::isMigrateArtisanCommand()) {
            return false;
        }

        return ! self::isPathLimitedMigrateCommand();
    }

    protected static function isPathLimitedMigrateCommand(): bool
    {
        return in_array('--path', $_SERVER['argv'] ?? [], true);
    }

    protected static function isMigrateArtisanCommand(): bool
    {
        if (! isset($_SERVER['argv'][1])) {
            return false;
        }

        $command = (string) $_SERVER['argv'][1];

        return $command === 'migrate'
            || str_starts_with($command, 'migrate:');
    }

    protected static function registerMigrateEndedListener(): void
    {
        if (self::$migrateListenerRegistered) {
            return;
        }

        self::$migrateListenerRegistered = true;

        Event::listen(MigrationsEnded::class, function (): void {
            if (! self::canProvision()) {
                return;
            }

            $sources = array_keys(self::$deferredSources);
            self::$deferredSources = [];
            $withLogos = self::$deferLogos;
            self::$deferLogos = false;

            foreach ($sources as $source) {
                self::provisionFor($source);
            }

            if ($withLogos || $sources !== []) {
                PluginVaultRootGroup::logos();
                SharedLogosGallery::album();
            }
        });
    }
}
