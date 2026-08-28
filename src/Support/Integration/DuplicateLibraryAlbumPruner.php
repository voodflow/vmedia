<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Illuminate\Support\Collection;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryUploadTarget;

/**
 * Removes duplicate empty "Library" albums created under the same plugin folder.
 */
final class DuplicateLibraryAlbumPruner
{
    public static function prune(): int
    {
        $pruned = 0;

        MediaGallery::query()
            ->where('kind', MediaGallery::KIND_GROUP)
            ->orderBy('id')
            ->each(function (MediaGallery $group) use (&$pruned): void {
                $pruned += self::pruneUnderGroup($group);
            });

        return $pruned;
    }

    protected static function pruneUnderGroup(MediaGallery $group): int
    {
        $scopedKey = 'group:'.(int) $group->getKey().':library';

        $libraries = $group->children()
            ->where('kind', MediaGallery::KIND_ALBUM)
            ->where(function ($query) use ($scopedKey, $group): void {
                $query->where('slug', 'library')
                    ->orWhere('slug', 'like', 'library-%')
                    ->orWhere('integration_key', $scopedKey)
                    ->orWhere('integration_key', 'library');

                if (filled($group->integration_source)) {
                    $query->orWhere(function ($query) use ($group): void {
                        $query->where('integration_source', (string) $group->integration_source)
                            ->whereIn('slug', ['library', 'library-1']);
                    });
                }
            })
            ->withCount('mediaItems')
            ->orderBy('id')
            ->get();

        if ($libraries->count() <= 1) {
            self::normalizeCanonicalAlbum($group, $libraries->first());

            return 0;
        }

        /** @var MediaGallery $canonical */
        $canonical = self::pickCanonicalAlbum($libraries, $scopedKey);

        $pruned = 0;

        foreach ($libraries as $duplicate) {
            if ((int) $duplicate->getKey() === (int) $canonical->getKey()) {
                continue;
            }

            foreach ($duplicate->mediaItems()->get() as $media) {
                $canonical->attachMedia([$media]);
            }

            $duplicate->delete();
            $pruned++;
        }

        self::normalizeCanonicalAlbum($group, $canonical);

        return $pruned;
    }

    protected static function normalizeCanonicalAlbum(MediaGallery $group, ?MediaGallery $album): void
    {
        if (! $album instanceof MediaGallery) {
            return;
        }

        $scopedKey = 'group:'.(int) $group->getKey().':library';
        $updates = [];

        if ($album->integration_key !== $scopedKey) {
            $updates['integration_key'] = $scopedKey;
        }

        if ($album->slug !== 'library') {
            $updates['slug'] = 'library';
        }

        if ($updates !== []) {
            if (filled($group->integration_source) && blank($album->integration_source)) {
                $updates['integration_source'] = (string) $group->integration_source;
            }

            $album->forceFill($updates)->save();
        }

        // Touch resolve so future uploads use the normalized album.
        GalleryUploadTarget::resolve((int) $group->getKey());
    }

    /**
     * @param  Collection<int, MediaGallery>  $libraries
     */
    protected static function pickCanonicalAlbum(Collection $libraries, string $scopedKey): MediaGallery
    {
        return $libraries
            ->sort(function (MediaGallery $a, MediaGallery $b) use ($scopedKey): int {
                return self::canonicalRank($a, $scopedKey) <=> self::canonicalRank($b, $scopedKey);
            })
            ->first();
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function canonicalRank(MediaGallery $album, string $scopedKey): array
    {
        return [
            $album->integration_key === $scopedKey ? 0 : 1,
            -((int) ($album->media_items_count ?? 0)),
            (int) $album->getKey(),
        ];
    }
}
