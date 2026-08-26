<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;

/**
 * Attach vault MediaItems to a domain model without Spatie HasMedia on that model.
 *
 * Logical collections (logo, gallery, …) live on the pivot; files stay on MediaVault.
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
            ->withPivot(['collection', 'sort_order'])
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

    public function attachMediaItem(string $collection, MediaItem $media, bool $replace = false): void
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
        ]);

        $this->unsetRelation('media');
    }

    /**
     * @param  list<int>  $mediaIds
     */
    public function syncMediaCollection(string $collection, array $mediaIds): void
    {
        $mediaIds = array_values(array_unique(array_filter(array_map('intval', $mediaIds))));

        $this->clearMediaCollection($collection);

        foreach ($mediaIds as $index => $id) {
            $this->media()->attach($id, [
                'collection' => $collection,
                'sort_order' => $index,
            ]);
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
            });

        $this->unsetRelation('media');
    }

    public function clearMediaCollection(string $collection): void
    {
        $this->vmediaPivotQuery()
            ->where('collection', $collection)
            ->delete();

        $this->unsetRelation('media');
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
