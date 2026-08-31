<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Voodflow\Vmedia\Support\GalleryIntegrationSchema;

/**
 * Idempotent top-level vault folders for voodflow companion plugins.
 */
final class PluginVaultRootBootstrap
{
    /** @var array<string, true> */
    private static array $deferredSources = [];

    private static bool $migrateListenerRegistered = false;

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
        if (self::shouldDeferUntilMigrateEnds()) {
            foreach ([
                'vexhibitors',
                'vevents',
                'vsponsors',
                'vpartners',
                'vtuts',
                'vdocs',
                'voodbuilder',
                'vforms',
            ] as $source) {
                self::$deferredSources[$source] = true;
            }

            self::registerMigrateEndedListener();

            return;
        }

        if (! self::canProvision()) {
            return;
        }

        foreach ([
            'vexhibitors',
            'vevents',
            'vsponsors',
            'vpartners',
            'vtuts',
            'vdocs',
            'voodbuilder',
            'vforms',
        ] as $source) {
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
        match ($integrationSource) {
            'vexhibitors' => PluginVaultRootGroup::exhibitors(),
            'vevents' => PluginVaultRootGroup::events(),
            'vsponsors' => PluginVaultRootGroup::sponsors(),
            'vpartners' => PluginVaultRootGroup::partners(),
            'vtuts' => PluginVaultRootGroup::vtuts(),
            'vdocs' => PluginVaultRootGroup::vdocs(),
            'voodbuilder' => PluginVaultRootGroup::voodbuilder(),
            'vforms' => PluginVaultRootGroup::vforms(),
            default => null,
        };
    }

    protected static function shouldDeferUntilMigrateEnds(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        if (! self::isMigrateArtisanCommand()) {
            return false;
        }

        // Targeted package/test migrations should not defer vault provisioning.
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

            foreach ($sources as $source) {
                self::provisionFor($source);
            }

            if ($sources !== []) {
                PluginVaultRootGroup::logos();
                SharedLogosGallery::album();
            }
        });
    }
}
