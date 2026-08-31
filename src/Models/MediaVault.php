<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\ImageOptimizer\OptimizerChain;
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
        return (string) config('vmedia.tables.vaults', 'vmedia_vaults');
    }

    /**
     * Stable morph alias for Spatie media rows owned by the vault.
     */
    public function getMorphClass(): string
    {
        return 'vmedia_vault';
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

        $optimize = (bool) config('vmedia.conversions.optimize', true);
        $queued = (bool) config('vmedia.conversions.queued', false);

        $thumbWidth = (int) config('vmedia.conversions.thumb.width', 400);
        $thumbHeight = (int) config('vmedia.conversions.thumb.height', 400);
        $thumbFormat = (string) config('vmedia.conversions.thumb.format', 'webp');

        $thumb = $this->addMediaConversion('thumb')
            ->width(max(1, $thumbWidth))
            ->height(max(1, $thumbHeight))
            ->format($thumbFormat)
            ->performOnCollections(MediaGallery::COLLECTION_IMAGES);

        // Spatie Image Optimizer (optional composer package).
        if ($optimize && class_exists(OptimizerChain::class)) {
            $thumb->optimize();
        }

        if (! $queued) {
            $thumb->nonQueued();
        }

        $previewWidth = (int) config('vmedia.conversions.preview.width', 0);

        if ($previewWidth <= 0) {
            return;
        }

        $previewHeight = (int) config('vmedia.conversions.preview.height', 1280);
        $previewFormat = (string) config('vmedia.conversions.preview.format', 'webp');

        $preview = $this->addMediaConversion('preview')
            ->width(max(1, $previewWidth))
            ->height(max(1, $previewHeight))
            ->format($previewFormat)
            ->performOnCollections(MediaGallery::COLLECTION_IMAGES);

        if ($optimize && class_exists(OptimizerChain::class)) {
            $preview->optimize();
        }

        if (! $queued) {
            $preview->nonQueued();
        }
    }
}
