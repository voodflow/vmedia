<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

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
}
