<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class MediaTag extends Model
{
    use HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return (string) config('vmedia.tables.tags', 'vmedia_tags');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return BelongsToMany<MediaGallery, $this>
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaGallery::class,
            (string) config('vmedia.tables.gallery_tags', 'vmedia_gallery_tags'),
            'tag_id',
            'gallery_id',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<MediaItem, $this>
     */
    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaItem::class,
            (string) config('vmedia.tables.media_tags', 'vmedia_media_tags'),
            'tag_id',
            'media_id',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<MediaGallery, $this>
     */
    public function allowedForGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaGallery::class,
            (string) config('vmedia.tables.gallery_allowed_tags', 'vmedia_gallery_allowed_tags'),
            'tag_id',
            'gallery_id',
        )->withTimestamps();
    }
}
