<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Voodflow\Vmedia\Events\MediaAttached;
use Voodflow\Vmedia\Events\MediaDetached;
use Voodflow\Vmedia\Models\MediaAttachment;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\AttachmentMeta;
use Voodflow\Vmedia\Support\MediaLibrary;

/**
 * Attach vault MediaItems to a domain model without Spatie HasMedia on that model.
 *
 * Logical collections (logo, gallery, …) live on the pivot; files stay on MediaVault.
 * Per-attachment caption/alt/credits live in pivot `properties` (override vault defaults).
 *
 * @mixin Model
 */
trait HasAttachedMedia
{
    /**
     * @return MorphToMany<MediaItem, $this>
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(
            MediaItem::class,
            'attachable',
            $this->vmediaAttachmentsTable(),
            'attachable_id',
            'media_id',
        )
            ->using(MediaAttachment::class)
            ->withPivot(['collection', 'sort_order', 'properties'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return Collection<int, MediaItem>
     */
    public function getMedia(string $collectionName = 'default'): Collection
    {
        $this->loadMissing('media');

        return $this->media
            ->filter(fn (MediaItem $item): bool => (string) $item->pivot->collection === $collectionName)
            ->values();
    }

    public function getFirstMedia(string $collectionName = 'default'): ?MediaItem
    {
        return $this->getMedia($collectionName)->first();
    }

    public function getFirstMediaUrl(string $collectionName = 'default'): string
    {
        $media = $this->getFirstMedia($collectionName);

        return $media instanceof MediaItem ? MediaLibrary::publicUrl($media) : '';
    }

    public function attachMediaItem(string $collection, MediaItem $media, bool $replace = false, array $properties = []): void
    {
        if ($replace) {
            $this->clearMediaCollection($collection);
        }

        $already = $this->getMedia($collection)->contains(
            fn (MediaItem $item): bool => (int) $item->getKey() === (int) $media->getKey(),
        );

        if ($already) {
            return;
        }

        $this->media()->attach((int) $media->getKey(), [
            'collection' => $collection,
            'sort_order' => $this->getMedia($collection)->count(),
            'properties' => AttachmentMeta::normalizeProperties($properties) ?: null,
        ]);

        $this->unsetRelation('media');
        MediaAttached::dispatch($this, $media, $collection);
    }

    /**
     * Sync collection membership + optional per-attachment properties.
     *
     * Each entry may be a media id (int) or `['id' => int, 'properties' => array|null]`.
     * When `properties` is omitted/null for an existing attachment, previous overrides are kept.
     *
     * @param  list<int|array{id: int, properties?: array<string, mixed>|null}>  $mediaIds
     */
    public function syncMediaCollection(string $collection, array $mediaIds): void
    {
        $normalized = [];

        foreach ($mediaIds as $entry) {
            if (is_array($entry)) {
                $id = (int) ($entry['id'] ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $normalized[] = [
                    'id' => $id,
                    'properties' => array_key_exists('properties', $entry)
                        ? (is_array($entry['properties']) ? AttachmentMeta::normalizeProperties($entry['properties']) : [])
                        : null,
                ];

                continue;
            }

            $id = (int) $entry;

            if ($id <= 0) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'properties' => null,
            ];
        }

        $unique = [];
        foreach ($normalized as $row) {
            $unique[$row['id']] = $row;
        }
        $normalized = array_values($unique);

        $existing = $this->vmediaPivotQuery()
            ->where('collection', $collection)
            ->get()
            ->keyBy(fn ($row): int => (int) $row->media_id);

        $keepIds = array_map(static fn (array $row): int => $row['id'], $normalized);

        foreach ($existing as $mediaId => $row) {
            if (in_array((int) $mediaId, $keepIds, true)) {
                continue;
            }

            $this->vmediaPivotQuery()
                ->where('collection', $collection)
                ->where('media_id', $mediaId)
                ->delete();

            $media = MediaItem::query()->find($mediaId);

            if ($media !== null) {
                MediaDetached::dispatch($this, $media, $collection);
            }
        }

        foreach ($normalized as $index => $item) {
            $existingRow = $existing->get($item['id']);
            $properties = $item['properties'];

            if ($properties === null) {
                $decoded = null;

                if ($existingRow !== null && isset($existingRow->properties)) {
                    $decoded = is_string($existingRow->properties)
                        ? json_decode($existingRow->properties, true)
                        : $existingRow->properties;
                }

                $properties = is_array($decoded) ? $decoded : [];
            }

            $payload = [
                'collection' => $collection,
                'sort_order' => $index,
                'properties' => $properties === [] ? null : $properties,
            ];

            if ($existingRow !== null) {
                $this->vmediaPivotQuery()
                    ->where('collection', $collection)
                    ->where('media_id', $item['id'])
                    ->update([
                        'sort_order' => $index,
                        'properties' => $payload['properties'] === null
                            ? null
                            : json_encode($payload['properties']),
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $this->media()->attach($item['id'], $payload);

            $media = MediaItem::query()->find($item['id']);

            if ($media !== null) {
                MediaAttached::dispatch($this, $media, $collection);
            }
        }

        $this->unsetRelation('media');
    }

    /**
     * @param  list<string>  $uuids
     */
    public function reorderMediaCollection(string $collection, array $uuids): void
    {
        foreach (array_values($uuids) as $index => $uuid) {
            $media = MediaItem::query()->where('uuid', $uuid)->first();

            if ($media === null) {
                continue;
            }

            $this->vmediaPivotQuery()
                ->where('collection', $collection)
                ->where('media_id', $media->getKey())
                ->update(['sort_order' => $index]);
        }

        $this->unsetRelation('media');
    }

    /**
     * Detach items not listed; never deletes vault files.
     *
     * @param  list<string>  $keepUuids
     */
    public function detachAbandonedMedia(string $collection, array $keepUuids): void
    {
        $keep = array_values(array_filter($keepUuids));

        $this->getMedia($collection)
            ->filter(fn (MediaItem $item): bool => ! in_array((string) $item->uuid, $keep, true))
            ->each(function (MediaItem $item) use ($collection): void {
                $this->vmediaPivotQuery()
                    ->where('collection', $collection)
                    ->where('media_id', $item->getKey())
                    ->delete();

                MediaDetached::dispatch($this, $item, $collection);
            });

        $this->unsetRelation('media');
    }

    public function clearMediaCollection(string $collection): void
    {
        $items = $this->getMedia($collection);

        $this->vmediaPivotQuery()
            ->where('collection', $collection)
            ->delete();

        $this->unsetRelation('media');

        foreach ($items as $item) {
            MediaDetached::dispatch($this, $item, $collection);
        }
    }

    protected function vmediaAttachmentsTable(): string
    {
        return (string) config('vmedia.tables.attachments', 'vmedia_attachments');
    }

    /**
     * @return Builder
     */
    protected function vmediaPivotQuery()
    {
        return DB::table($this->vmediaAttachmentsTable())
            ->where('attachable_type', $this->getMorphClass())
            ->where('attachable_id', $this->getKey());
    }
}
