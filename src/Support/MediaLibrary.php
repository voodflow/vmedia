<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;
use Voodflow\VoodbuilderMedia\Models\MediaItem;
use Voodflow\VoodbuilderMedia\Models\MediaVault;

/**
 * Shared list/store helpers for admin + VoodBuilder editor Asset Manager.
 */
final class MediaLibrary
{
    /**
     * @return list<array{id: int, name: string, slug: string, is_default: bool, is_public: bool, media_count: int}>
     */
    public static function listGalleries(?string $type = null): array
    {
        $query = MediaGallery::query()
            ->withCount(['mediaItems as media_count' => function ($builder) use ($type): void {
                if ($type === 'image') {
                    $builder->where('collection_name', MediaGallery::COLLECTION_IMAGES);
                } elseif ($type === 'video') {
                    $builder->where('collection_name', MediaGallery::COLLECTION_VIDEOS);
                }
            }])
            ->orderBy('sort_order')
            ->orderBy('id');

        return $query->get()->map(static fn (MediaGallery $gallery): array => [
            'id' => (int) $gallery->getKey(),
            'name' => (string) $gallery->name,
            'slug' => (string) $gallery->slug,
            'is_default' => (bool) $gallery->is_default,
            'is_public' => (bool) $gallery->is_public,
            'media_count' => (int) ($gallery->media_count ?? 0),
        ])->all();
    }

    /**
     * @return array{
     *   data: list<array{src: string, type: string, name: string, caption: string|null, uuid: string, id: int, gallery_ids: list<int>, thumb: string|null}>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     * }
     */
    public static function paginateAssets(
        ?string $type = null,
        ?int $galleryId = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 48,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(96, $perPage));

        $query = MediaItem::query()
            ->with('galleries:id,name')
            ->where('model_type', (new MediaVault)->getMorphClass())
            ->whereIn('collection_name', [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
            ])
            ->latest('id');

        if ($galleryId !== null) {
            $query->whereHas('galleries', static fn ($builder) => $builder->whereKey($galleryId));
        }

        if ($type === 'image') {
            $query->where('collection_name', MediaGallery::COLLECTION_IMAGES);
        } elseif ($type === 'video') {
            $query->where('collection_name', MediaGallery::COLLECTION_VIDEOS);
        }

        if (filled($search)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('file_name', 'like', $term)
                    ->orWhere('custom_properties->caption', 'like', $term);
            });
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return [
            'data' => $paginator->getCollection()
                ->map(static fn (MediaItem $media): array => self::toAssetPayload($media))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @return list<array{src: string, type: string, name: string, caption: string|null, uuid: string, id: int, gallery_ids: list<int>, thumb: string|null}>
     */
    public static function listAssets(?string $type = null, ?int $galleryId = null): array
    {
        return self::paginateAssets($type, $galleryId, null, 1, 5000)['data'];
    }

    /**
     * @return array{src: string, type: string, name: string, caption: string|null, uuid: string, id: int, gallery_ids: list<int>, thumb: string|null}
     */
    public static function toAssetPayload(MediaItem $media): array
    {
        if (! $media->relationLoaded('galleries')) {
            $media->load('galleries:id');
        }

        $src = self::publicUrl($media);
        $isVideo = $media->isVideo();

        return [
            'src' => $src,
            'type' => $isVideo ? 'video' : 'image',
            'name' => $media->displayTitle(),
            'caption' => $media->caption(),
            'uuid' => (string) $media->uuid,
            'id' => (int) $media->getKey(),
            'gallery_ids' => $media->galleries->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            // Images: same URL (browser lazy-loads). Videos: null — show icon until selected.
            'thumb' => $isVideo ? null : $src,
        ];
    }

    /**
     * Store on the vault and attach to one or more galleries (default when empty).
     *
     * Display title (`name`) stays human-readable; `file_name` is always a storage hash.
     *
     * @param  iterable<int|MediaGallery>|null  $galleries
     */
    public static function store(
        UploadedFile $file,
        iterable|MediaGallery|null $galleries = null,
        ?string $name = null,
        ?string $caption = null,
    ): MediaItem {
        $targets = self::normalizeGalleries($galleries);
        $mime = (string) ($file->getMimeType() ?? '');
        $isVideo = str_starts_with($mime, 'video/');
        $collection = $isVideo ? MediaGallery::COLLECTION_VIDEOS : MediaGallery::COLLECTION_IMAGES;
        $displayName = self::resolveDisplayName($file, $name);

        $adder = MediaVault::current()
            ->addMedia($file)
            ->usingName($displayName)
            ->usingFileName($file->hashName());

        if (filled($caption)) {
            $adder->withCustomProperties([
                MediaItem::CUSTOM_CAPTION => trim($caption),
            ]);
        }

        /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $stored */
        $stored = $adder->toMediaCollection($collection);

        $media = MediaItem::query()->findOrFail($stored->getKey());

        foreach ($targets as $gallery) {
            $gallery->attachMedia([$media]);
        }

        return $media->load('galleries');
    }

    /**
     * Prefer an explicit title, then the client filename stem (never a bare "edited").
     */
    public static function resolveDisplayName(UploadedFile $file, ?string $name = null): string
    {
        $explicit = is_string($name) ? trim($name) : '';

        if ($explicit !== '') {
            return pathinfo($explicit, PATHINFO_FILENAME) ?: $explicit;
        }

        $fromClient = trim((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($fromClient !== '' && ! preg_match('/^edited(?:[-_.].*)?$/i', $fromClient)) {
            return $fromClient;
        }

        if ($fromClient !== '' && preg_match('/^edited[-_.](.+)$/i', $fromClient, $matches)) {
            return 'edited-'.$matches[1];
        }

        return 'image';
    }

    /**
     * @param  Collection<int, MediaItem>|iterable<MediaItem>  $media
     * @param  list<int>  $galleryIds
     */
    public static function assignGalleries(iterable $media, array $galleryIds, bool $replace = false): void
    {
        $galleryIds = array_values(array_unique(array_filter(array_map('intval', $galleryIds))));

        foreach ($media as $item) {
            if (! $item instanceof MediaItem) {
                continue;
            }

            if ($replace) {
                $item->galleries()->sync($galleryIds);

                continue;
            }

            $item->galleries()->syncWithoutDetaching($galleryIds);
        }
    }

    /**
     * @param  Collection<int, MediaItem>|iterable<MediaItem>  $media
     */
    public static function createGalleryWithMedia(string $name, iterable $media, ?string $description = null): MediaGallery
    {
        $gallery = MediaGallery::query()->create([
            'name' => $name,
            'description' => $description,
            'is_default' => false,
            'is_public' => true,
            'sort_order' => (int) (MediaGallery::query()->max('sort_order') ?? 0) + 1,
        ]);

        $gallery->attachMedia($media);

        return $gallery;
    }

    public static function publicUrl(MediaItem $media): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', (string) $media->getPathRelativeToRoot()), '/');
    }

    /**
     * @param  iterable<int|MediaGallery>|MediaGallery|null  $galleries
     * @return list<MediaGallery>
     */
    protected static function normalizeGalleries(iterable|MediaGallery|null $galleries): array
    {
        if ($galleries instanceof MediaGallery) {
            return [$galleries];
        }

        if ($galleries === null) {
            return [MediaGallery::default()];
        }

        $resolved = [];

        foreach ($galleries as $gallery) {
            if ($gallery instanceof MediaGallery) {
                $resolved[] = $gallery;

                continue;
            }

            $found = MediaGallery::query()->find((int) $gallery);

            if ($found !== null) {
                $resolved[] = $found;
            }
        }

        return $resolved !== [] ? $resolved : [MediaGallery::default()];
    }
}
