<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
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

    public const COLLECTION_FILES = 'files';

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
        return (string) config('vmedia.tables.galleries', 'voodbuilder_media_galleries');
    }

    /**
     * Stable morph alias (kept for legacy migrations / existing rows).
     */
    public function getMorphClass(): string
    {
        return 'voodbuilder_media_gallery';
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
            (string) config('vmedia.tables.gallery_media', 'voodbuilder_media_gallery_media'),
            'gallery_id',
            'media_id',
        )
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Attach media without detaching others (idempotent). New items go at the end.
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

        $table = (string) config('vmedia.tables.gallery_media', 'voodbuilder_media_gallery_media');
        $existingMax = (int) (DB::table($table)
            ->where('gallery_id', $this->getKey())
            ->max('sort_order') ?? -1);

        $already = DB::table($table)
            ->where('gallery_id', $this->getKey())
            ->whereIn('media_id', $ids)
            ->pluck('media_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $attach = [];
        $order = $existingMax + 1;

        foreach ($ids as $id) {
            if (in_array($id, $already, true)) {
                continue;
            }

            $attach[$id] = ['sort_order' => $order++];
        }

        if ($attach !== []) {
            $this->mediaItems()->attach($attach);
        }
    }

    /**
     * Replace gallery membership preserving explicit order (index = sort_order).
     *
     * @param  list<int|string>  $mediaIdsOrUuids  media ids or UUIDs
     */
    public function syncOrderedMedia(array $mediaIdsOrUuids): void
    {
        $ids = [];

        foreach ($mediaIdsOrUuids as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ids[] = (int) $value;

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $id = MediaItem::query()->where('uuid', $value)->value('id');

            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        $unique = [];
        foreach ($ids as $id) {
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }
        $ids = array_values($unique);

        $sync = [];
        foreach ($ids as $index => $id) {
            $sync[$id] = ['sort_order' => $index];
        }

        $this->mediaItems()->sync($sync);
        $this->unsetRelation('mediaItems');
    }

    /**
     * @return list<string>
     */
    public function orderedMediaUuids(): array
    {
        return $this->mediaItems()
            ->get()
            ->map(fn (MediaItem $media): string => (string) $media->uuid)
            ->values()
            ->all();
    }
}
