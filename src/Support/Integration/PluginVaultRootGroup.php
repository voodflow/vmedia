<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\EntityVaultGallery;
use Voodflow\Vmedia\Support\GalleryProvisioner;

/**
 * Top-level vault folder per plugin (Exhibitors, Sponsors, Partners) with brand albums nested inside.
 */
final class PluginVaultRootGroup
{
    public static function exhibitors(?int $outerRootId = null): MediaGallery
    {
        return self::ensure(
            integrationSource: 'vexhibitors',
            integrationKey: 'root:exhibitors',
            name: (string) __('vmedia::admin.plugin_roots.exhibitors'),
            slug: 'exhibitors',
            outerRootId: $outerRootId,
        );
    }

    public static function sponsors(?int $outerRootId = null): MediaGallery
    {
        return self::ensure(
            integrationSource: 'vsponsors',
            integrationKey: 'root:sponsors',
            name: (string) __('vmedia::admin.plugin_roots.sponsors'),
            slug: 'sponsors',
            outerRootId: $outerRootId,
        );
    }

    public static function partners(?int $outerRootId = null): MediaGallery
    {
        return self::ensure(
            integrationSource: 'vpartners',
            integrationKey: 'root:partners',
            name: (string) __('vmedia::admin.plugin_roots.partners'),
            slug: 'partners',
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
