<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\EntityVaultGallery;
use Voodflow\Vmedia\Support\GalleryProvisioner;

/**
 * Top-level vault folder per voodflow plugin, plus a shared Logos folder for brand marks.
 */
final class PluginVaultRootGroup
{
    public static function exhibitors(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vexhibitors',
            integrationKey: 'root:exhibitors',
            name: (string) __('vmedia::admin.plugin_roots.exhibitors'),
            slug: 'exhibitors',
            outerRootId: $outerRootId,
        );
    }

    public static function events(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vevents',
            integrationKey: 'root:events',
            name: (string) __('vmedia::admin.plugin_roots.events'),
            slug: 'events',
            outerRootId: $outerRootId,
        );
    }

    public static function sponsors(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vsponsors',
            integrationKey: 'root:sponsors',
            name: (string) __('vmedia::admin.plugin_roots.sponsors'),
            slug: 'sponsors',
            outerRootId: $outerRootId,
        );
    }

    public static function partners(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vpartners',
            integrationKey: 'root:partners',
            name: (string) __('vmedia::admin.plugin_roots.partners'),
            slug: 'partners',
            outerRootId: $outerRootId,
        );
    }

    public static function vtuts(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vtuts',
            integrationKey: 'root:vtuts',
            name: (string) __('vmedia::admin.plugin_roots.vtuts'),
            slug: 'vtuts',
            outerRootId: $outerRootId,
        );
    }

    public static function vdocs(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vdocs',
            integrationKey: 'root:vdocs',
            name: (string) __('vmedia::admin.plugin_roots.vdocs'),
            slug: 'vdocs',
            outerRootId: $outerRootId,
        );
    }

    public static function voodbuilder(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'voodbuilder',
            integrationKey: 'root:voodbuilder',
            name: (string) __('vmedia::admin.plugin_roots.voodbuilder'),
            slug: 'voodbuilder',
            outerRootId: $outerRootId,
        );
    }

    public static function vforms(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
            integrationSource: 'vforms',
            integrationKey: 'root:vforms',
            name: (string) __('vmedia::admin.plugin_roots.vforms'),
            slug: 'vforms',
            outerRootId: $outerRootId,
        );
    }

    /**
     * Shared logos folder — integration keys are global so the first plugin needing logos creates it.
     */
    public static function logos(?int $outerRootId = null): MediaGallery
    {
        return self::pluginRoot(
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
