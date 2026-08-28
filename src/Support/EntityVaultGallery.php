<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Idempotent per-entity vault album (exhibitor, sponsor, partner profile media).
 */
final class EntityVaultGallery
{
    /**
     * @param  array<string, scalar|null>  $tokens
     */
    public static function ensure(
        string $integrationSource,
        string $integrationKey,
        string $name,
        string $slug,
        ?MediaGallery $parentGroup = null,
        ?int $rootParentId = null,
        array $tokens = [],
    ): MediaGallery {
        $parent = $parentGroup ?? GalleryProvisioner::resolveParent($rootParentId);

        $resolvedName = GalleryProvisioner::renderTemplate($name, $tokens);
        $resolvedSlug = GalleryProvisioner::renderTemplate($slug, $tokens);

        if ($resolvedName === '') {
            $resolvedName = $integrationKey;
        }

        if ($resolvedSlug === '') {
            $resolvedSlug = $integrationKey;
        }

        $gallery = GalleryProvisioner::ensure([
            'name' => $resolvedName,
            'slug' => $resolvedSlug,
            'kind' => MediaGallery::KIND_ALBUM,
            'parent' => $parent,
            'integration_source' => $integrationSource,
            'integration_key' => $integrationKey,
        ]);

        return self::syncParent($gallery, $parent);
    }

    public static function syncParent(MediaGallery $gallery, ?MediaGallery $parent): MediaGallery
    {
        if ($parent === null) {
            return $gallery;
        }

        $parentId = (int) $parent->getKey();

        if ((int) $gallery->parent_id !== $parentId || (int) $gallery->parent_key !== $parentId) {
            $gallery->forceFill([
                'parent_id' => $parentId,
                'parent_key' => $parentId,
            ])->save();
        }

        return $gallery;
    }
}
