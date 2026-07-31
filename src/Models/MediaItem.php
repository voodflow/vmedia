<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Spatie Media row with gallery memberships (many-to-many).
 */
class MediaItem extends Media
{
    /**
     * @return BelongsToMany<MediaGallery, $this>
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaGallery::class,
            (string) config('voodbuilder-media.tables.gallery_media', 'voodbuilder_media_gallery_media'),
            'media_id',
            'gallery_id',
        )
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function isVideo(): bool
    {
        return $this->collection_name === MediaGallery::COLLECTION_VIDEOS
            || str_starts_with((string) $this->mime_type, 'video/');
    }

    public function kindLabel(): string
    {
        return $this->isVideo()
            ? (string) __('voodbuilder-media::admin.library.videos')
            : (string) __('voodbuilder-media::admin.library.photos');
    }
}
