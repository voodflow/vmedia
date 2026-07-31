<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Gallery / album: organizational membership of media (many-to-many).
 * Files themselves live on {@see MediaVault}.
 */
class MediaGallery extends Model
{
    use HasSlug;

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
        // Unique by default (suffix -1, -2…); call allowDuplicateSlugs() to opt out.
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
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

    /**
     * @return BelongsToMany<MediaItem, $this>
     */
    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaItem::class,
            (string) config('voodbuilder-media.tables.gallery_media', 'voodbuilder_media_gallery_media'),
            'gallery_id',
            'media_id',
        )
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Attach media without detaching others (idempotent).
     *
     * @param  iterable<int|MediaItem>  $media
     */
    public function attachMedia(iterable $media): void
    {
        $ids = [];

        foreach ($media as $item) {
            $ids[] = $item instanceof MediaItem ? (int) $item->getKey() : (int) $item;
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return;
        }

        $this->mediaItems()->syncWithoutDetaching($ids);
    }
}
