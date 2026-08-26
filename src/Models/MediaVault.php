<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Singleton Spatie owner for all library files (galleries are membership only).
 */
class MediaVault extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
    ];

    public function getTable(): string
    {
        return (string) config('vmedia.tables.vaults', 'voodbuilder_media_vaults');
    }

    /**
     * Stable morph alias (kept for existing Spatie media rows).
     */
    public function getMorphClass(): string
    {
        return 'voodbuilder_media_vault';
    }

    public static function current(): self
    {
        $existing = static::query()->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::query()->create([
            'name' => 'Media vault',
        ]);
    }

    public function registerMediaCollections(): void
    {
        $disk = (string) config('vmedia.disk', 'public');

        $this->addMediaCollection(MediaGallery::COLLECTION_IMAGES)
            ->useDisk($disk);

        $this->addMediaCollection(MediaGallery::COLLECTION_VIDEOS)
            ->useDisk($disk);

        $this->addMediaCollection(MediaGallery::COLLECTION_FILES)
            ->useDisk($disk);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! (bool) config('vmedia.conversions.enabled', true)) {
            return;
        }

        $width = (int) config('vmedia.conversions.thumb.width', 400);
        $height = (int) config('vmedia.conversions.thumb.height', 400);
        $format = (string) config('vmedia.conversions.thumb.format', 'webp');

        $conversion = $this->addMediaConversion('thumb')
            ->width(max(1, $width))
            ->height(max(1, $height))
            ->format($format)
            ->performOnCollections(MediaGallery::COLLECTION_IMAGES);

        if (! (bool) config('vmedia.conversions.queued', false)) {
            $conversion->nonQueued();
        }
    }
}
