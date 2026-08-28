<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Support\Str;
use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Idempotent gallery provisioning for host plugins (vexhibitors, vevents, …).
 */
final class GalleryProvisioner
{
    /**
     * @param  array<string, scalar|null>  $tokens
     */
    public static function renderTemplate(string $template, array $tokens): string
    {
        $rendered = $template;

        foreach ($tokens as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $rendered = str_replace('{'.$key.'}', (string) ($value ?? ''), $rendered);
        }

        return trim(preg_replace('/\s+/u', ' ', $rendered) ?? $rendered);
    }

    /**
     * @param  array{
     *   name: string,
     *   slug?: string|null,
     *   description?: string|null,
     *   kind?: string,
     *   is_public?: bool,
     *   sort_order?: int|null,
     *   parent?: MediaGallery|null,
     *   integration_source?: string|null,
     *   integration_key?: string|null,
     * }  $attributes
     */
    public static function ensure(array $attributes): MediaGallery
    {
        $source = filled($attributes['integration_source'] ?? null)
            ? (string) $attributes['integration_source']
            : null;
        $key = filled($attributes['integration_key'] ?? null)
            ? (string) $attributes['integration_key']
            : null;

        if ($source !== null && $key !== null) {
            $existing = MediaGallery::query()
                ->where('integration_source', $source)
                ->where('integration_key', $key)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        /** @var MediaGallery|null $parent */
        $parent = $attributes['parent'] ?? null;
        $kind = ($attributes['kind'] ?? MediaGallery::KIND_ALBUM) === MediaGallery::KIND_GROUP
            ? MediaGallery::KIND_GROUP
            : MediaGallery::KIND_ALBUM;

        $name = trim((string) ($attributes['name'] ?? ''));
        $slug = filled($attributes['slug'] ?? null)
            ? Str::slug((string) $attributes['slug'])
            : Str::slug($name);

        return MediaGallery::query()->create([
            'parent_id' => $parent?->getKey(),
            'parent_key' => (int) ($parent?->getKey() ?? 0),
            'name' => $name,
            'slug' => $slug,
            'kind' => $kind,
            'description' => $attributes['description'] ?? null,
            'is_default' => false,
            'is_public' => (bool) ($attributes['is_public'] ?? true),
            'sort_order' => (int) ($attributes['sort_order'] ?? ((int) (MediaGallery::query()->max('sort_order') ?? 0) + 1)),
            'integration_source' => $source,
            'integration_key' => $key,
        ]);
    }

    public static function resolveParent(?int $parentId): ?MediaGallery
    {
        if ($parentId === null || $parentId <= 0) {
            return null;
        }

        $parent = MediaGallery::query()->find($parentId);

        return $parent?->isGroup() ? $parent : null;
    }
}
