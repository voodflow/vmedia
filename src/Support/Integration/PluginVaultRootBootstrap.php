<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Illuminate\Support\Facades\Schema;
use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Idempotent top-level vault folders for voodflow companion plugins.
 */
final class PluginVaultRootBootstrap
{
    public static function ensureFor(string $integrationSource): void
    {
        if (! self::canProvision()) {
            return;
        }

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

    public static function ensureAll(): void
    {
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
            self::ensureFor($source);
        }

        PluginVaultRootGroup::logos();
        SharedLogosGallery::album();
    }

    protected static function canProvision(): bool
    {
        if (! class_exists(MediaGallery::class)) {
            return false;
        }

        $table = (string) config('vmedia.tables.galleries', 'vmedia_galleries');

        // Hierarchy columns are added in a later migration; companions boot during migrate.
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'integration_source');
    }
}
