<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Constraint;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Voodflow\Vmedia\Support\ConversionLadder;

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

        /*
         * Ladder widths are maximum targets, never upscale instructions.
         * Without `DoNotUpsize`, a 1536px original is blown up to the `xl` 4096px
         * canvas: GD then allocates ~90MB for the bitmap plus a similar buffer for
         * the encoder, which OOMs the request on the default 128M memory_limit.
         */
        $constraints = [Constraint::PreserveAspectRatio, Constraint::DoNotUpsize];

        foreach (ConversionLadder::definitions() as $definition) {
            $conversion = $this->addMediaConversion($definition['key'])
                ->width(max(1, $definition['width']), $constraints)
                ->format($definition['format'])
                ->performOnCollections(MediaGallery::COLLECTION_IMAGES);

            if ($definition['height'] > 0) {
                $conversion->height($definition['height'], $constraints);
            }

            if ($optimize && class_exists(OptimizerChain::class)) {
                $conversion->optimize();
            }

            if (! $queued) {
                $conversion->nonQueued();
            }
        }
    }
}
