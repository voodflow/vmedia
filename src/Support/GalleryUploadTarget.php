<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Resolve which album receives new uploads for a browse context (album, folder, or default).
 */
final class GalleryUploadTarget
{
    public static function resolve(?int $galleryId): MediaGallery
    {
        if ($galleryId === null || $galleryId <= 0) {
            return MediaGallery::default();
        }

        $gallery = MediaGallery::query()->find($galleryId);

        if ($gallery === null) {
            return MediaGallery::default();
        }

        if ($gallery->isAlbum()) {
            return $gallery;
        }

        return self::resolveAlbumForGroup($gallery);
    }

    public static function resolveFromBrowse(
        ?int $parentFolderId,
        ?int $galleryId,
        ?int $fallbackGalleryId = null,
    ): MediaGallery {
        if ($galleryId !== null && $galleryId > 0) {
            return self::resolve($galleryId);
        }

        if ($parentFolderId !== null && $parentFolderId > 0) {
            return self::resolve($parentFolderId);
        }

        if ($fallbackGalleryId !== null && $fallbackGalleryId > 0) {
            return self::resolve($fallbackGalleryId);
        }

        return MediaGallery::default();
    }

    /**
     * @return array{id: int, name: string, path: string, kind: string}
     */
    public static function payload(?int $parentFolderId, ?int $galleryId, ?int $fallbackGalleryId = null): array
    {
        $target = self::resolveFromBrowse($parentFolderId, $galleryId, $fallbackGalleryId);

        return [
            'id' => (int) $target->getKey(),
            'name' => (string) $target->name,
            'path' => GalleryPath::toPath($target),
            'kind' => (string) $target->kind,
        ];
    }

    protected static function resolveAlbumForGroup(MediaGallery $group): MediaGallery
    {
        $library = MediaGallery::query()
            ->where('parent_id', $group->getKey())
            ->where('kind', MediaGallery::KIND_ALBUM)
            ->where(function ($query): void {
                $query->where('slug', 'library')
                    ->orWhere('integration_key', 'library');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($library instanceof MediaGallery) {
            return $library;
        }

        $firstAlbum = MediaGallery::query()
            ->where('parent_id', $group->getKey())
            ->where('kind', MediaGallery::KIND_ALBUM)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($firstAlbum instanceof MediaGallery) {
            return $firstAlbum;
        }

        return GalleryProvisioner::ensure([
            'name' => (string) __('vmedia::admin.plugin_roots.library'),
            'slug' => 'library',
            'kind' => MediaGallery::KIND_ALBUM,
            'parent' => $group,
            'integration_source' => filled($group->integration_source) ? (string) $group->integration_source : 'vmedia',
            'integration_key' => 'group:'.$group->getKey().':library',
        ]);
    }
}
