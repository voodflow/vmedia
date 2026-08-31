<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;

/**
 * Reverse lookups: where vault media is attached / whether it is safe to delete.
 */
final class MediaUsage
{
    public static function attachmentCount(MediaItem $media): int
    {
        return (int) DB::table(self::attachmentsTable())
            ->where('media_id', $media->getKey())
            ->count();
    }

    /**
     * @return Collection<int, object{attachable_type: string, attachable_id: int|string, collection: string}>
     */
    public static function attachments(MediaItem $media): Collection
    {
        return DB::table(self::attachmentsTable())
            ->where('media_id', $media->getKey())
            ->orderBy('attachable_type')
            ->orderBy('attachable_id')
            ->get(['attachable_type', 'attachable_id', 'collection']);
    }

    public static function galleryCount(MediaItem $media): int
    {
        return (int) $media->galleries()->count();
    }

    public static function totalReferences(MediaItem $media): int
    {
        return self::attachmentCount($media) + self::galleryCount($media);
    }

    public static function isUsed(MediaItem $media): bool
    {
        return self::attachmentCount($media) > 0;
    }

    /**
     * Vault media with no gallery membership and no domain attachments.
     */
    public static function isOrphan(MediaItem $media): bool
    {
        if (! MediaLibrary::isVaultMedia($media)) {
            return false;
        }

        return self::galleryCount($media) === 0 && self::attachmentCount($media) === 0;
    }

    public static function detachAllAttachments(MediaItem $media): int
    {
        return (int) DB::table(self::attachmentsTable())
            ->where('media_id', $media->getKey())
            ->delete();
    }

    /**
     * @return array{media: int, images: int, videos: int, files: int, galleries: int, bytes: int, orphans: int, trashed: int}
     */
    public static function stats(): array
    {
        $vaultMorph = (new MediaVault)->getMorphClass();
        $base = MediaItem::query()->where('model_type', $vaultMorph);

        $images = (clone $base)->where('collection_name', 'images')->count();
        $videos = (clone $base)->where('collection_name', 'videos')->count();
        $files = (clone $base)->where('collection_name', 'files')->count();
        $bytes = (int) (clone $base)->sum('size');
        $trashed = MediaItem::onlyTrashed()->where('model_type', $vaultMorph)->count();

        $orphanIds = self::orphanQuery()->pluck('id');

        return [
            'media' => $images + $videos + $files,
            'images' => $images,
            'videos' => $videos,
            'files' => $files,
            'galleries' => (int) MediaGallery::query()->count(),
            'bytes' => $bytes,
            'orphans' => $orphanIds->count(),
            'trashed' => $trashed,
        ];
    }

    /**
     * @return Builder<MediaItem>
     */
    public static function orphanQuery()
    {
        $vaultMorph = (new MediaVault)->getMorphClass();
        $galleryPivot = (string) config('vmedia.tables.gallery_media', 'vmedia_gallery_media');
        $attachments = self::attachmentsTable();

        return MediaItem::query()
            ->where('model_type', $vaultMorph)
            ->whereIn('collection_name', ['images', 'videos', 'files'])
            ->whereNotExists(function ($query) use ($galleryPivot): void {
                $query->selectRaw('1')
                    ->from($galleryPivot)
                    ->whereColumn($galleryPivot.'.media_id', 'media.id');
            })
            ->whereNotExists(function ($query) use ($attachments): void {
                $query->selectRaw('1')
                    ->from($attachments)
                    ->whereColumn($attachments.'.media_id', 'media.id');
            });
    }

    protected static function attachmentsTable(): string
    {
        return (string) config('vmedia.tables.attachments', 'vmedia_attachments');
    }
}
