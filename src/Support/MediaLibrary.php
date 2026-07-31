<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Support;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;

/**
 * Shared list/store helpers for admin + VoodBuilder editor Asset Manager.
 */
final class MediaLibrary
{
    /**
     * @return list<array{src: string, type: string, name: string, uuid: string, id: int, gallery_id: int}>
     */
    public static function listAssets(?string $type = null, ?int $galleryId = null): array
    {
        $query = Media::query()
            ->where('model_type', (new MediaGallery)->getMorphClass())
            ->whereIn('collection_name', [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
            ])
            ->latest('id');

        if ($galleryId !== null) {
            $query->where('model_id', $galleryId);
        }

        if ($type === 'image') {
            $query->where('collection_name', MediaGallery::COLLECTION_IMAGES);
        } elseif ($type === 'video') {
            $query->where('collection_name', MediaGallery::COLLECTION_VIDEOS);
        }

        $assets = [];

        foreach ($query->get() as $media) {
            $assets[] = self::toAssetPayload($media);
        }

        return $assets;
    }

    public static function store(UploadedFile $file, ?MediaGallery $gallery = null): Media
    {
        $gallery ??= MediaGallery::default();
        $mime = (string) ($file->getMimeType() ?? '');
        $isVideo = str_starts_with($mime, 'video/');
        $collection = $isVideo ? MediaGallery::COLLECTION_VIDEOS : MediaGallery::COLLECTION_IMAGES;

        return $gallery
            ->addMedia($file)
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $file->hashName())
            ->usingFileName($file->hashName())
            ->toMediaCollection($collection);
    }

    /**
     * @return array{src: string, type: string, name: string, uuid: string, id: int, gallery_id: int}
     */
    public static function toAssetPayload(Media $media): array
    {
        $type = str_starts_with((string) $media->mime_type, 'video/')
            || $media->collection_name === MediaGallery::COLLECTION_VIDEOS
            ? 'video'
            : 'image';

        return [
            'src' => self::publicUrl($media),
            'type' => $type,
            'name' => (string) ($media->name ?: $media->file_name),
            'uuid' => (string) $media->uuid,
            'id' => (int) $media->getKey(),
            'gallery_id' => (int) $media->model_id,
        ];
    }

    public static function publicUrl(Media $media): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', (string) $media->getPathRelativeToRoot()), '/');
    }
}
