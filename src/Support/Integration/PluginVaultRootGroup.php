<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\EntityVaultGallery;
use Voodflow\Vmedia\Support\GalleryProvisioner;

/**
 * Top-level vault folders: plugin-registered roots + shared Logos (owned by VoodMedia).
 */
final class PluginVaultRootGroup
{
    /**
     * Ensure the registered plugin root folder exists (and is synced).
     */
    public static function for(string $integrationSource, ?int $outerRootId = null): MediaGallery
    {
        $definition = PluginVaultRegistry::get($integrationSource);

        return self::ensure(
            integrationSource: $definition['source'],
            integrationKey: $definition['key'],
            name: PluginVaultRegistry::resolveName($definition['name']),
            slug: $definition['slug'],
            outerRootId: $outerRootId,
        );
    }

    /**
     * Shared logos folder — integration keys are global so the first consumer creates it.
     */
    public static function logos(?int $outerRootId = null): MediaGallery
    {
        return self::ensure(
            integrationSource: 'vmedia',
            integrationKey: 'root:logos',
            name: (string) __('vmedia::admin.plugin_roots.logos'),
            slug: 'logos',
            outerRootId: $outerRootId,
        );
    }

    /**
     * Settings override first; otherwise the plugin root folder becomes the default parent.
     */
    public static function resolveParentOrPluginRoot(?int $rootParentId, callable $rootFactory): ?MediaGallery
    {
        $configured = GalleryProvisioner::resolveParent($rootParentId);

        if ($configured !== null) {
            return $configured;
        }

        $root = $rootFactory();

        return $root->isGroup() ? $root : null;
    }

    public static function pluginRoot(
        string $integrationSource,
        string $integrationKey,
        string $name,
        string $slug,
        ?int $outerRootId = null,
    ): MediaGallery {
        return self::ensure(
            integrationSource: $integrationSource,
            integrationKey: $integrationKey,
            name: $name,
            slug: $slug,
            outerRootId: $outerRootId,
        );
    }

    public static function ensure(
        string $integrationSource,
        string $integrationKey,
        string $name,
        string $slug,
        ?int $outerRootId = null,
    ): MediaGallery {
        $outerParent = GalleryProvisioner::resolveParent($outerRootId);

        $group = GalleryProvisioner::ensure([
            'name' => $name,
            'slug' => $slug,
            'kind' => MediaGallery::KIND_GROUP,
            'parent' => $outerParent,
            'integration_source' => $integrationSource,
            'integration_key' => $integrationKey,
        ]);

        if ($group->name !== $name) {
            $group->name = $name;
            $group->save();
        }

        return EntityVaultGallery::syncParent($group, $outerParent);
    }
}
