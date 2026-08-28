<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\EntityVaultGallery;

/**
 * Shared logos album under the global Logos vault folder (created once, reused by all plugins).
 */
final class SharedLogosGallery
{
    public static function album(): MediaGallery
    {
        $group = PluginVaultRootGroup::logos();

        return EntityVaultGallery::ensure(
            integrationSource: 'vmedia',
            integrationKey: 'logos:library',
            name: (string) __('vmedia::admin.plugin_roots.logos_library'),
            slug: 'library',
            parentGroup: $group,
        );
    }
}
