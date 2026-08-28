<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Builder;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaTag;
use Voodflow\Vmedia\Models\MediaVault;

/**
 * Resolve hierarchical gallery paths and descendant album ids.
 */
final class GalleryPath
{
    /**
     * Resolve a slash-separated public path (e.g. "2024/queen").
     */
    public static function resolve(string $path): ?MediaGallery
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if ($segments === []) {
            return null;
        }

        $parentKey = 0;
        $node = null;

        foreach ($segments as $segment) {
            $node = MediaGallery::query()
                ->where('parent_key', $parentKey)
                ->where('slug', $segment)
                ->first();

            if ($node === null) {
                return null;
            }

            $parentKey = (int) $node->getKey();
        }

        return $node;
    }

    /**
     * @return list<string>
     */
    public static function segments(MediaGallery $gallery): array
    {
        $segments = [];
        $current = $gallery->loadMissing('parent');

        while ($current !== null) {
            array_unshift($segments, (string) $current->slug);
            $current = $current->parent;
            $current?->loadMissing('parent');
        }

        return $segments;
    }

    public static function toPath(MediaGallery $gallery): string
    {
        return implode('/', self::segments($gallery));
    }

    public static function depth(MediaGallery $gallery): int
    {
        return max(0, count(self::segments($gallery)) - 1);
    }

    /**
     * @return list<int>
     */
    public static function descendantAlbumIds(MediaGallery $root, bool $includeSelf = true): array
    {
        $ids = [];

        if ($includeSelf && $root->isAlbum()) {
            $ids[] = (int) $root->getKey();
        }

        self::collectDescendantAlbumIds($root, $ids);

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $ids
     */
    protected static function collectDescendantAlbumIds(MediaGallery $node, array &$ids): void
    {
        $children = $node->relationLoaded('children')
            ? $node->children
            : $node->children()->get();

        foreach ($children as $child) {
            if ($child->isAlbum()) {
                $ids[] = (int) $child->getKey();
            }

            if ($child->isGroup()) {
                self::collectDescendantAlbumIds($child, $ids);
            }
        }
    }

    /**
     * Allowed tag ids for a gallery node (union of ancestor group definitions).
     *
     * @return list<int>
     */
    public static function effectiveAllowedTagIds(?MediaGallery $gallery): array
    {
        if ($gallery === null) {
            return MediaTag::query()->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $ids = [];
        $current = $gallery->loadMissing(['parent', 'allowedTags']);

        while ($current !== null) {
            if ($current->isGroup()) {
                foreach ($current->allowedTags as $tag) {
                    $ids[(int) $tag->getKey()] = (int) $tag->getKey();
                }
            }

            $current = $current->parent;
            $current?->loadMissing(['parent', 'allowedTags']);
        }

        if ($ids === []) {
            return MediaTag::query()->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return array_values($ids);
    }

    /**
     * @return Builder<MediaTag>
     */
    public static function allowedTagsQuery(?MediaGallery $gallery): Builder
    {
        $allowed = self::effectiveAllowedTagIds($gallery);

        return MediaTag::query()->whereIn('id', $allowed)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return list<array{id: int, name: string, slug: string, kind: string, parent_id: int|null, depth: int, path: string, media_count: int, children_count: int}>
     */
    public static function treePayload(?int $parentId = null): array
    {
        $nodes = MediaGallery::query()
            ->withCount(['children', 'mediaItems'])
            ->when(
                $parentId === null,
                fn (Builder $query) => $query->where(function (Builder $builder): void {
                    $builder->whereNull('parent_id')->orWhere('parent_key', 0);
                }),
                fn (Builder $query) => $query->where('parent_id', $parentId),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $payload = [];

        foreach ($nodes as $node) {
            $payload[] = [
                'id' => (int) $node->getKey(),
                'name' => (string) $node->name,
                'slug' => (string) $node->slug,
                'kind' => (string) $node->kind,
                'parent_id' => $node->parent_id !== null ? (int) $node->parent_id : null,
                'depth' => self::depth($node),
                'path' => self::toPath($node),
                'media_count' => (int) ($node->media_items_count ?? 0),
                'children_count' => (int) ($node->children_count ?? 0),
            ];
        }

        return $payload;
    }

    /**
     * @return list<int>
     */
    public static function descendantGroupAndAlbumIds(MediaGallery $root): array
    {
        $ids = [(int) $root->getKey()];
        self::collectAllDescendantIds($root, $ids);

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $ids
     */
    protected static function collectAllDescendantIds(MediaGallery $node, array &$ids): void
    {
        $children = $node->children()->get();

        foreach ($children as $child) {
            $ids[] = (int) $child->getKey();
            self::collectAllDescendantIds($child, $ids);
        }
    }
}
