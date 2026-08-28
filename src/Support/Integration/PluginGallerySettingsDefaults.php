<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

final class PluginGallerySettingsDefaults
{
    /** @return array<string, mixed> */
    public static function vexhibitors(): array
    {
        return (new PluginGallerySettings(
            enabled: true,
            rootParentId: null,
            autoCreateGroup: true,
            groupNameTemplate: '{event.title}',
            groupSlugTemplate: '{event.slug}',
            autoCreateAlbum: true,
            albumNameTemplate: '{exhibitor.company_name}',
            albumSlugTemplate: '{exhibitor.slug}',
        ))->toArray();
    }

    /** @return array<string, mixed> */
    public static function vevents(): array
    {
        return (new PluginGallerySettings(
            enabled: true,
            rootParentId: null,
            autoCreateGroup: true,
            groupNameTemplate: '{event.title}',
            groupSlugTemplate: '{event.slug}',
            autoCreateAlbum: false,
            albumNameTemplate: '{entity.name}',
            albumSlugTemplate: '{entity.slug}',
        ))->toArray();
    }

    /** @return array<string, mixed> */
    public static function vsponsors(): array
    {
        return (new PluginGallerySettings(
            enabled: false,
            rootParentId: null,
            autoCreateGroup: true,
            groupNameTemplate: '{event.title}',
            groupSlugTemplate: '{event.slug}',
            autoCreateAlbum: true,
            albumNameTemplate: '{entity.name}',
            albumSlugTemplate: '{entity.slug}',
        ))->toArray();
    }
}
