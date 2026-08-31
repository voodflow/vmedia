<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Builder;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaTag;
use Voodflow\Vmedia\Models\MediaVault;

/**
 * Aggregate media from a group and all descendant albums (paginated, filterable).
 */
final class GalleryAggregate
{
    /**
     * @param  array{
     *   order?: 'manual'|'newest'|'oldest'|'random',
     *   tag_ids?: list<int>,
     *   tag_match?: 'any'|'all',
     *   type?: 'image'|'video'|'file'|null,
     *   search?: string|null,
     *   page?: int,
     *   per_page?: int,
     *   include_self?: bool,
     * }  $options
     * @return array{
     *   data: list<array<string, mixed>>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool},
     *   gallery: MediaGallery,
     *   album_ids: list<int>
     * }
     */
    public static function paginateMedia(MediaGallery $group, array $options = []): array
    {
        $page = max(1, (int) ($options['page'] ?? 1));
        $perPage = max(1, min(96, (int) ($options['per_page'] ?? config('vmedia.browser.per_page', 48))));
        $order = (string) ($options['order'] ?? 'manual');
        $includeSelf = (bool) ($options['include_self'] ?? true);
        $albumIds = GalleryPath::descendantAlbumIds($group, $includeSelf);

        if ($albumIds === []) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'has_more' => false,
                ],
                'gallery' => $group,
                'album_ids' => [],
            ];
        }

        $query = self::baseMediaQuery($albumIds, $options);
        $mediaTable = (new MediaItem)->getTable();

        if ($order === 'random') {
            $query->inRandomOrder();
        } elseif ($order === 'newest') {
            $query->latest("{$mediaTable}.id");
        } elseif ($order === 'oldest') {
            $query->oldest("{$mediaTable}.id");
        } else {
            $pivotTable = (string) config('vmedia.tables.gallery_media', 'vmedia_gallery_media');
            $albumIdList = implode(',', array_map(intval(...), $albumIds));

            $query->orderByRaw(
                "(SELECT MIN(sort_order) FROM {$pivotTable} AS gm WHERE gm.media_id = {$mediaTable}.id AND gm.gallery_id IN ({$albumIdList}))"
            )->orderBy("{$mediaTable}.id");
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return [
            'data' => $paginator->getCollection()
                ->map(static fn (MediaItem $media): array => MediaLibrary::toAssetPayload($media))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'gallery' => $group,
            'album_ids' => $albumIds,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public static function listMedia(MediaGallery $group, array $options = []): array
    {
        $options['page'] = 1;
        $options['per_page'] = (int) ($options['limit'] ?? 5000);

        return self::paginateMedia($group, $options)['data'];
    }

    /**
     * @param  list<int>  $albumIds
     * @param  array<string, mixed>  $options
     * @return Builder<MediaItem>
     */
    protected static function baseMediaQuery(array $albumIds, array $options): Builder
    {
        $galleryTable = (new MediaGallery)->getTable();
        $mediaTable = (new MediaItem)->getTable();
        $tagTable = (new MediaTag)->getTable();

        $query = MediaItem::query()
            ->with('galleries:id,name')
            ->where("{$mediaTable}.model_type", (new MediaVault)->getMorphClass())
            ->whereIn("{$mediaTable}.collection_name", [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
                MediaGallery::COLLECTION_FILES,
            ])
            ->whereHas('galleries', fn (Builder $builder): Builder => $builder->whereIn("{$galleryTable}.id", $albumIds));

        $type = $options['type'] ?? null;

        if ($type === 'image') {
            $query->where("{$mediaTable}.collection_name", MediaGallery::COLLECTION_IMAGES);
        } elseif ($type === 'video') {
            $query->where("{$mediaTable}.collection_name", MediaGallery::COLLECTION_VIDEOS);
        } elseif ($type === 'file') {
            $query->where("{$mediaTable}.collection_name", MediaGallery::COLLECTION_FILES);
        }

        $search = $options['search'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
            $query->where(function (Builder $builder) use ($term, $mediaTable): void {
                $builder
                    ->where("{$mediaTable}.name", 'like', $term)
                    ->orWhere("{$mediaTable}.file_name", 'like', $term)
                    ->orWhere("{$mediaTable}.custom_properties->caption", 'like', $term)
                    ->orWhere("{$mediaTable}.custom_properties->alt", 'like', $term);
            });
        }

        $tagIds = array_values(array_filter(array_map('intval', (array) ($options['tag_ids'] ?? []))));

        if ($tagIds !== []) {
            $match = ($options['tag_match'] ?? 'any') === 'all' ? 'all' : 'any';

            if ($match === 'all') {
                foreach ($tagIds as $tagId) {
                    $query->whereHas('tags', fn (Builder $builder): Builder => $builder->where("{$tagTable}.id", $tagId));
                }
            } else {
                $query->whereHas('tags', fn (Builder $builder): Builder => $builder->whereIn("{$tagTable}.id", $tagIds));
            }
        }

        return $query;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function childAlbumPayloads(MediaGallery $group): array
    {
        return $group->children()
            ->withCount('mediaItems')
            ->get()
            ->map(static fn (MediaGallery $child): array => [
                'id' => (int) $child->getKey(),
                'name' => (string) $child->name,
                'slug' => (string) $child->slug,
                'kind' => (string) $child->kind,
                'path' => GalleryPath::toPath($child),
                'description' => $child->description,
                'media_count' => (int) ($child->media_items_count ?? 0),
                'cover' => self::firstAssetForAlbum($child),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function firstAssetForAlbum(MediaGallery $album): ?array
    {
        if ($album->isGroup()) {
            return null;
        }

        $media = $album->mediaItems()->first();

        return $media instanceof MediaItem ? MediaLibrary::toAssetPayload($media) : null;
    }
}
