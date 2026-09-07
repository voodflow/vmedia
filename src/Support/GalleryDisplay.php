<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Builder;
use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Human-readable gallery labels and hierarchical ordering for browser sidebars.
 */
final class GalleryDisplay
{
    /**
     * Full breadcrumb using gallery display names (e.g. "Builder › Library").
     */
    public static function breadcrumb(MediaGallery $gallery): string
    {
        $chain = [];
        $current = $gallery->loadMissing('parent');

        while ($current !== null) {
            array_unshift($chain, (string) $current->name);
            $current = $current->parent;
            $current?->loadMissing('parent');
        }

        return implode(' › ', $chain);
    }

    /**
     * @return array<int, string> Album id → label (folders excluded).
     */
    public static function albumSelectOptions(): array
    {
        $albums = MediaGallery::query()
            ->with(['parent:id,name,parent_id'])
            ->where('kind', MediaGallery::KIND_ALBUM)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $options = [];

        foreach ($albums as $album) {
            $options[(int) $album->getKey()] = self::navLabel($album);
        }

        return $options;
    }

    /**
     * Compact label for sidebar / select options.
     *
     * Root nodes use their name. Nested albums include the parent folder for disambiguation
     * (e.g. multiple "Library" albums under different plugin folders).
     */
    public static function navLabel(MediaGallery $gallery): string
    {
        $name = (string) $gallery->name;

        if ($gallery->isGroup()) {
            $depth = GalleryPath::depth($gallery);

            if ($depth === 0) {
                return $name;
            }

            return self::breadcrumb($gallery);
        }

        $gallery->loadMissing('parent');
        $parent = $gallery->parent;

        if ($parent instanceof MediaGallery) {
            return $parent->name.' › '.$name;
        }

        if ($name !== '' && GalleryPath::toPath($gallery) !== '' && GalleryPath::toPath($gallery) !== strtolower($name)) {
            return $name.' · '.GalleryPath::toPath($gallery);
        }

        return $name;
    }

    /**
     * Depth-first order with explicit depth for indentation in JS/CSS.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public static function orderHierarchically(array $nodes): array
    {
        if ($nodes === []) {
            return [];
        }

        $byParent = [];

        foreach ($nodes as $node) {
            $parentId = $node['parent_id'] ?? null;
            $key = is_numeric($parentId) ? (int) $parentId : 0;
            $byParent[$key][] = $node;
        }

        $ordered = [];

        $walk = function (?int $parentId, int $depth) use (&$walk, &$ordered, $byParent): void {
            $key = $parentId ?? 0;

            foreach ($byParent[$key] ?? [] as $node) {
                $node['depth'] = $depth;
                $ordered[] = $node;

                if (($node['kind'] ?? '') === MediaGallery::KIND_GROUP) {
                    $walk((int) $node['id'], $depth + 1);
                }
            }
        };

        $walk(null, 0);

        return $ordered;
    }

    /**
     * Depth-first friendly ordering for admin lists and browsers.
     */
    public static function applyTreeOrdering(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
