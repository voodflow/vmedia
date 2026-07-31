<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Gallery / album that owns reusable Spatie media (images + videos).
 */
class MediaGallery extends Model implements HasMedia
{
    use HasSlug;
    use InteractsWithMedia;

    public const COLLECTION_IMAGES = 'images';

    public const COLLECTION_VIDEOS = 'videos';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'sort_order',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return (string) config('voodbuilder-media.tables.galleries', 'voodbuilder_media_galleries');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public static function default(): self
    {
        $existing = static::query()->where('is_default', true)->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        $first = static::query()->orderBy('sort_order')->orderBy('id')->first();

        if ($first !== null) {
            if (! $first->is_default) {
                $first->forceFill(['is_default' => true])->save();
            }

            return $first;
        }

        return static::query()->create([
            'name' => 'Library',
            'description' => 'Default shared media library',
            'is_default' => true,
            'is_public' => true,
            'sort_order' => 0,
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (MediaGallery $gallery): void {
            if (! $gallery->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($gallery->getKey() ?? 0)
                ->update(['is_default' => false]);
        });
    }

    public function registerMediaCollections(): void
    {
        $disk = (string) config('voodbuilder-media.disk', 'public');

        $this->addMediaCollection(self::COLLECTION_IMAGES)
            ->useDisk($disk);

        $this->addMediaCollection(self::COLLECTION_VIDEOS)
            ->useDisk($disk);
    }

}
