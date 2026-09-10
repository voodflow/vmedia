<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Voodflow\Vmedia\Events\MediaDeleted;
use Voodflow\Vmedia\Events\MediaRestored;
use Voodflow\Vmedia\Events\MediaStored;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;

/**
 * Shared list/store helpers for admin + optional page-builder Asset Manager.
 */
final class MediaLibrary
{
    /**
     * @return list<array{id: int, name: string, slug: string, kind: string, parent_id: int|null, parent_name: string|null, path: string, depth: int, label: string, breadcrumb: string, is_default: bool, is_public: bool, media_count: int, children_count: int}>
     */
    public static function listGalleries(?string $type = null): array
    {
        $query = MediaGallery::query()
            ->with(['parent:id,name'])
            ->withCount(['mediaItems as media_count' => function ($builder) use ($type): void {
                if ($type === 'image') {
                    $builder->where('collection_name', MediaGallery::COLLECTION_IMAGES);
                } elseif ($type === 'video') {
                    $builder->where('collection_name', MediaGallery::COLLECTION_VIDEOS);
                } elseif ($type === 'file') {
                    $builder->where('collection_name', MediaGallery::COLLECTION_FILES);
                }
            }, 'children'])
            ->orderBy('sort_order')
            ->orderBy('id');

        return $query->get()->map(static fn (MediaGallery $gallery): array => [
            'id' => (int) $gallery->getKey(),
            'name' => (string) $gallery->name,
            'slug' => (string) $gallery->slug,
            'kind' => (string) $gallery->kind,
            'parent_id' => $gallery->parent_id !== null ? (int) $gallery->parent_id : null,
            'parent_name' => $gallery->parent?->name,
            'path' => GalleryPath::toPath($gallery),
            'depth' => GalleryPath::depth($gallery),
            'label' => GalleryDisplay::navLabel($gallery),
            'breadcrumb' => GalleryDisplay::breadcrumb($gallery),
            'is_default' => (bool) $gallery->is_default,
            'is_public' => (bool) $gallery->is_public,
            'media_count' => (int) ($gallery->media_count ?? 0),
            'children_count' => (int) ($gallery->children_count ?? 0),
        ])->pipe(static fn ($collection): array => GalleryDisplay::orderHierarchically($collection->all()));
    }

    /**
     * @return array{
     *   data: list<array{src: string, type: string, name: string, caption: string|null, alt: string|null, uuid: string, id: int, gallery_ids: list<int>, thumb: string|null, poster: string|null, object_position: string|null, icon: string, icon_label: string}>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     * }
     */
    public static function paginateAssets(
        ?string $type = null,
        ?int $galleryId = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 48,
        bool $includeDescendants = false,
        array $tagIds = [],
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(96, $perPage));

        if ($galleryId !== null && $includeDescendants) {
            $gallery = MediaGallery::query()->find($galleryId);

            if ($gallery !== null) {
                return GalleryAggregate::paginateMedia($gallery, [
                    'type' => $type,
                    'search' => $search,
                    'page' => $page,
                    'per_page' => $perPage,
                    'tag_ids' => $tagIds,
                ]);
            }
        }

        $query = MediaItem::query()
            ->with(['galleries:id,name', 'tags:id,name'])
            ->where('model_type', (new MediaVault)->getMorphClass())
            ->whereIn('collection_name', [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
                MediaGallery::COLLECTION_FILES,
            ])
            ->latest('id');

        if ($galleryId !== null) {
            $query->whereHas('galleries', static fn ($builder) => $builder->whereKey($galleryId));
        }

        if ($tagIds !== []) {
            $query->whereHas('tags', static fn ($builder) => $builder->whereIn('id', $tagIds));
        }

        if ($type === 'image') {
            $query->where('collection_name', MediaGallery::COLLECTION_IMAGES);
        } elseif ($type === 'video') {
            $query->where('collection_name', MediaGallery::COLLECTION_VIDEOS);
        } elseif ($type === 'file') {
            $query->where('collection_name', MediaGallery::COLLECTION_FILES);
        }

        if (filled($search)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('file_name', 'like', $term)
                    ->orWhere('custom_properties->caption', 'like', $term)
                    ->orWhere('custom_properties->alt', 'like', $term);
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
     * @return list<array{src: string, type: string, name: string, caption: string|null, alt: string|null, uuid: string, id: int, gallery_ids: list<int>, thumb: string|null, poster: string|null, object_position: string|null, icon: string, icon_label: string}>
     */
    public static function listAssets(?string $type = null, ?int $galleryId = null): array
    {
        return self::paginateAssets($type, $galleryId, null, 1, 5000)['data'];
    }

    /**
     * @return array{src: string, type: string, name: string, file_name: string, caption: string|null, alt: string|null, credits: string|null, uuid: string, id: int, gallery_ids: list<int>, thumb: string|null, poster: string|null, object_position: string|null, icon: string, icon_label: string}
     */
    public static function toAssetPayload(MediaItem $media): array
    {
        if (! $media->relationLoaded('galleries')) {
            $media->load('galleries:id');
        }

        $src = self::publicUrl($media);
        $type = self::assetType($media);
        $icon = FileTypeIcon::forMedia($media);

        return [
            'src' => $src,
            'type' => $type,
            'name' => $media->displayTitle(),
            'file_name' => (string) $media->file_name,
            'caption' => $media->caption(),
            'alt' => $media->alt(),
            'credits' => $media->credits(),
            'uuid' => (string) $media->uuid,
            'id' => (int) $media->getKey(),
            'gallery_ids' => $media->galleries->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'thumb' => self::thumbUrl($media),
            'poster' => self::posterUrl($media),
            'object_position' => $media->objectPositionCss(),
            'icon' => $icon['icon'],
            'icon_label' => $icon['icon_label'],
        ];
    }

    /**
     * Slim payload for picker / gallery browse tiles.
     *
     * @param  array{uuid: string, name: string, thumb?: string|null, poster?: string|null, src: string, type: string, icon?: string, icon_label?: string}  $asset
     * @return array{uuid: string, name: string, thumb: string|null, src: string, type: string, icon: string, icon_label: string}
     */
    public static function toBrowserTile(array $asset): array
    {
        return [
            'uuid' => $asset['uuid'],
            'name' => $asset['name'],
            'thumb' => $asset['thumb'] ?? $asset['poster'] ?? null,
            'src' => $asset['src'],
            'type' => $asset['type'],
            'icon' => $asset['icon'] ?? FileTypeIcon::FALLBACK,
            'icon_label' => $asset['icon_label'] ?? 'FILE',
        ];
    }

    public static function assetType(MediaItem $media): string
    {
        if ($media->isVideo()) {
            return 'video';
        }

        if ($media->isFile()) {
            return 'file';
        }

        return 'image';
    }

    /**
     * Store on the vault and attach to one or more galleries (default when empty).
     *
     * Display title (`name`) stays human-readable; `file_name` is always a storage hash.
     *
     * @param  iterable<int|MediaGallery>|null  $galleries
     * @param  array<string, mixed>  $customProperties
     */
    public static function store(
        UploadedFile $file,
        iterable|MediaGallery|null $galleries = null,
        ?string $name = null,
        ?string $caption = null,
        array $customProperties = [],
    ): MediaItem {
        UploadGuard::assertSafeUpload($file);
        UploadGuard::assertAllowedMime($file);
        // Before hashing, so dedup keys the bytes we are about to store rather than the
        // hostile original — otherwise a cleaned upload could be deduplicated against a
        // dirty one stored earlier.
        UploadGuard::sanitizeSvgInPlace($file);

        $targets = self::normalizeGalleries($galleries);
        $hash = self::contentHashForUpload($file);

        if (
            $hash !== null
            && (bool) config('vmedia.duplicates.detect', true)
            && (bool) config('vmedia.duplicates.reuse', true)
        ) {
            $existing = self::findByContentHash($hash);

            if ($existing !== null) {
                foreach ($targets as $gallery) {
                    $gallery->attachMedia([$existing]);
                }

                return $existing->load('galleries');
            }
        }

        $mime = (string) ($file->getMimeType() ?? '');
        $collection = self::collectionForMime($mime);
        $displayName = self::resolveDisplayName($file, $name);

        if ($hash !== null && ! array_key_exists(MediaItem::CUSTOM_CONTENT_HASH, $customProperties)) {
            $customProperties[MediaItem::CUSTOM_CONTENT_HASH] = $hash;
        }

        $adder = MediaVault::current()
            ->addMedia($file)
            ->usingName($displayName)
            ->usingFileName($file->hashName());

        if (filled($caption) && ! array_key_exists(MediaItem::CUSTOM_CAPTION, $customProperties)) {
            $customProperties[MediaItem::CUSTOM_CAPTION] = trim($caption);
        }

        if ($customProperties !== []) {
            $adder->withCustomProperties($customProperties);
        }

        /** @var Media $stored */
        $stored = $adder->toMediaCollection($collection);

        $media = MediaItem::query()->findOrFail($stored->getKey());

        foreach ($targets as $gallery) {
            $gallery->attachMedia([$media]);
        }

        $media = $media->load('galleries');
        MediaStored::dispatch($media);

        return $media;
    }

    public static function findByContentHash(string $hash): ?MediaItem
    {
        return MediaItem::query()
            ->where('model_type', (new MediaVault)->getMorphClass())
            ->where('custom_properties->'.MediaItem::CUSTOM_CONTENT_HASH, $hash)
            ->first();
    }

    public static function contentHashForUpload(UploadedFile $file): ?string
    {
        if (! (bool) config('vmedia.duplicates.detect', true)) {
            return null;
        }

        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $hash = hash_file('sha256', $path);

        return is_string($hash) ? $hash : null;
    }

    /**
     * Prefer an explicit title, then the client filename stem (never a bare "edited").
     */
    public static function resolveDisplayName(UploadedFile $file, ?string $name = null): string
    {
        $explicit = is_string($name) ? trim($name) : '';

        if ($explicit !== '') {
            $safe = UploadGuard::containsPathTraversal($explicit) ? 'image' : $explicit;

            return pathinfo($safe, PATHINFO_FILENAME) ?: $safe;
        }

        $fromClient = trim((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if (UploadGuard::containsPathTraversal($fromClient)) {
            return 'image';
        }

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
            'kind' => MediaGallery::KIND_ALBUM,
            'is_default' => false,
            'is_public' => true,
            'sort_order' => (int) (MediaGallery::query()->max('sort_order') ?? 0) + 1,
            'parent_key' => 0,
        ]);

        $gallery->attachMedia($media);

        return $gallery;
    }

    public static function publicUrl(MediaItem $media): string
    {
        $relative = UploadGuard::assertSafeRelativePath((string) $media->getPathRelativeToRoot());

        return self::browserUrl(
            Storage::disk((string) $media->disk)->url($relative),
        );
    }

    public static function thumbUrl(MediaItem $media): ?string
    {
        if ($media->isVideo() || $media->isFile()) {
            return self::posterUrl($media);
        }

        if (
            (bool) config('vmedia.conversions.enabled', true)
            && $media->hasGeneratedConversion('thumb')
        ) {
            try {
                $relative = UploadGuard::assertSafeRelativePath(
                    (string) $media->getPathRelativeToRoot('thumb'),
                );

                return self::browserUrl(
                    Storage::disk((string) $media->disk)->url($relative),
                );
            } catch (\Throwable) {
                // Fall through to original.
            }
        }

        return self::publicUrl($media);
    }

    /**
     * Prefer root-relative `/storage/...` for the local public disk so thumbs
     * and embeds survive APP_URL / Docker port mismatches. Keep absolute URLs
     * for remote disks (S3, CDN, …).
     */
    public static function browserUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }

    public static function posterUrl(MediaItem $media): ?string
    {
        $uuid = $media->posterUuid();

        if ($uuid === null) {
            return null;
        }

        $poster = MediaItem::query()->where('uuid', $uuid)->first();

        if ($poster === null || $poster->isVideo() || $poster->isFile()) {
            return null;
        }

        return self::thumbUrl($poster) ?? self::publicUrl($poster);
    }

    /**
     * Replace vault image bytes in-place (same id/uuid and gallery memberships).
     * On first edit, keeps one on-disk backup under `.originals/` next to the file.
     */
    public static function replaceFile(MediaItem $media, UploadedFile $file, ?string $name = null): MediaItem
    {
        if (! self::isVaultMedia($media)) {
            throw ValidationException::withMessages([
                'media' => ['Not a vault media item.'],
            ]);
        }

        if (! $media->isImage()) {
            throw ValidationException::withMessages([
                'file' => ['Only images can be replaced through the editor.'],
            ]);
        }

        UploadGuard::assertSafeUpload($file);
        UploadGuard::assertAllowedMime($file);
        UploadGuard::sanitizeSvgInPlace($file);

        self::ensureOriginalBackup($media);

        $disk = Storage::disk($media->disk);
        $relativePath = UploadGuard::assertSafeRelativePath((string) $media->getPathRelativeToRoot());

        $disk->putFileAs(dirname($relativePath), $file, basename($relativePath));

        $props = (array) $media->custom_properties;
        $hash = self::contentHashForUpload($file);

        if ($hash !== null) {
            $props[MediaItem::CUSTOM_CONTENT_HASH] = $hash;
        }

        $props[MediaItem::CUSTOM_EDITED_AT] = now()->toIso8601String();

        $updates = [
            'size' => $file->getSize(),
            'mime_type' => (string) ($file->getMimeType() ?? $media->mime_type),
            'custom_properties' => $props,
        ];

        if (filled($name)) {
            $updates['name'] = self::resolveDisplayName($file, $name);
        }

        $media->forceFill($updates)->save();

        $fresh = $media->fresh(['galleries']);

        return $fresh instanceof MediaItem ? $fresh : $media;
    }

    protected static function ensureOriginalBackup(MediaItem $media): void
    {
        $props = (array) $media->custom_properties;

        if (isset($props[MediaItem::CUSTOM_ORIGINAL_BACKUP_PATH])) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $source = UploadGuard::assertSafeRelativePath((string) $media->getPathRelativeToRoot());

        if (! $disk->exists($source)) {
            return;
        }

        $extension = pathinfo((string) $media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
        $backupPath = dirname($source).'/.originals/'.($media->uuid).'.'.$extension;

        if (! $disk->exists($backupPath)) {
            $disk->makeDirectory(dirname($backupPath));
            $disk->copy($source, $backupPath);
        }

        $absolute = $disk->path($source);

        if (is_file($absolute)) {
            $hash = hash_file('sha256', $absolute);

            if (is_string($hash)) {
                $props[MediaItem::CUSTOM_ORIGINAL_CONTENT_HASH] = $hash;
            }
        }

        $props[MediaItem::CUSTOM_ORIGINAL_BACKUP_PATH] = $backupPath;
        $media->forceFill(['custom_properties' => $props])->save();
    }

    public static function delete(MediaItem $media, bool $force = false): void
    {
        if (! self::isVaultMedia($media)) {
            throw ValidationException::withMessages([
                'media' => ['Not a vault media item.'],
            ]);
        }

        if (MediaUsage::isUsed($media)) {
            MediaUsage::detachAllAttachments($media);
        }

        if ($force) {
            $media->forceDelete();
            MediaDeleted::dispatch($media, true);

            return;
        }

        if ((bool) config('vmedia.soft_deletes', true)) {
            $media->delete();
            MediaDeleted::dispatch($media, false);

            return;
        }

        $media->forceDelete();
        MediaDeleted::dispatch($media, true);
    }

    public static function restore(MediaItem $media): void
    {
        if (! $media->trashed()) {
            return;
        }

        $media->restore();
        MediaRestored::dispatch($media);
    }

    public static function isVaultMedia(MediaItem $media): bool
    {
        $vaultMorph = (new MediaVault)->getMorphClass();

        return (string) $media->model_type === $vaultMorph
            && in_array($media->collection_name, [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
                MediaGallery::COLLECTION_FILES,
            ], true);
    }

    public static function collectionForMime(string $mime): string
    {
        if (str_starts_with($mime, 'video/')) {
            return MediaGallery::COLLECTION_VIDEOS;
        }

        if (str_starts_with($mime, 'image/')) {
            return MediaGallery::COLLECTION_IMAGES;
        }

        return MediaGallery::COLLECTION_FILES;
    }

    /**
     * @param  iterable<int|MediaGallery>|MediaGallery|null  $galleries
     * @return list<MediaGallery>
     */
    protected static function normalizeGalleries(iterable|MediaGallery|null $galleries): array
    {
        if ($galleries instanceof MediaGallery) {
            $galleries = [$galleries];
        }

        if ($galleries === null) {
            return [MediaGallery::default()];
        }

        $resolved = [];
        $seen = [];

        foreach ($galleries as $gallery) {
            if (! $gallery instanceof MediaGallery) {
                $gallery = MediaGallery::query()->find((int) $gallery);
            }

            if ($gallery === null) {
                continue;
            }

            // Folders cannot hold media — map to the scoped Library album (or first album).
            $album = $gallery->isGroup()
                ? GalleryUploadTarget::resolve((int) $gallery->getKey())
                : $gallery;

            $id = (int) $album->getKey();

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $resolved[] = $album;
        }

        return $resolved !== [] ? $resolved : [MediaGallery::default()];
    }
}
