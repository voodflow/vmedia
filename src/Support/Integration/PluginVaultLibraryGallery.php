<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use InvalidArgumentException;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\EntityVaultGallery;

/**
 * Default upload/browse album inside each plugin vault root folder.
 */
final class PluginVaultLibraryGallery
{
    public static function album(string $integrationSource): MediaGallery
    {
        $group = match ($integrationSource) {
            'vexhibitors' => PluginVaultRootGroup::exhibitors(),
            'vevents' => PluginVaultRootGroup::events(),
            'vsponsors' => PluginVaultRootGroup::sponsors(),
            'vpartners' => PluginVaultRootGroup::partners(),
            'vtuts' => PluginVaultRootGroup::vtuts(),
            'vdocs' => PluginVaultRootGroup::vdocs(),
            'voodbuilder' => PluginVaultRootGroup::voodbuilder(),
            'vforms' => PluginVaultRootGroup::vforms(),
            default => throw new InvalidArgumentException("Unknown vmedia vault plugin [{$integrationSource}]."),
        };

        return EntityVaultGallery::ensure(
            integrationSource: $integrationSource,
            integrationKey: 'library',
            name: (string) __('vmedia::admin.plugin_roots.library'),
            slug: 'library',
            parentGroup: $group,
        );
    }
}
