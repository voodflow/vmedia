<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Gallery / album: organizational membership of media (many-to-many).
 * Files themselves live on {@see MediaVault}.
 *
 * Groups ({@see KIND_GROUP}) organize child nodes only.
 * Albums ({@see KIND_ALBUM}) hold media items.
 */
class MediaGallery extends Model
{
    use HasSlug;

    public const KIND_GROUP = 'group';

    public const KIND_ALBUM = 'album';

    public const COLLECTION_IMAGES = 'images';

    public const COLLECTION_VIDEOS = 'videos';

    public const COLLECTION_FILES = 'files';

    protected $attributes = [
        'sort_order' => 0,
        'is_public' => true,
        'kind' => self::KIND_ALBUM,
        'parent_key' => 0,
    ];

    protected $fillable = [
        'parent_id',
        'parent_key',
        'name',
        'slug',
        'kind',
        'description',
        'is_default',
        'sort_order',
        'is_public',
        'integration_source',
        'integration_key',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'parent_key' => 'integer',
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
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->extraScope(fn ($builder) => $builder->where(
                'parent_key',
                (int) ($this->parent_key ?? ($this->parent_id ?? 0)),
            ));
    }

    public function isGroup(): bool
    {
        return $this->kind === self::KIND_GROUP;
    }

    public function isAlbum(): bool
    {
        return $this->kind !== self::KIND_GROUP;
    }

    /**
     * @return BelongsTo<MediaGallery, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaGallery, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return BelongsToMany<MediaTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaTag::class,
            (string) config('vmedia.tables.gallery_tags', 'vmedia_gallery_tags'),
            'gallery_id',
            'tag_id',
        )->withTimestamps();
    }

    /**
     * Tags selectable for albums/media under this group (includes inherited ancestors).
     *
     * @return BelongsToMany<MediaTag, $this>
     */
    public function allowedTags(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaTag::class,
            (string) config('vmedia.tables.gallery_allowed_tags', 'vmedia_gallery_allowed_tags'),
            'gallery_id',
            'tag_id',
        )->withTimestamps();
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
            'kind' => self::KIND_ALBUM,
            'is_default' => true,
            'is_public' => true,
            'sort_order' => 0,
            'parent_key' => 0,
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (MediaGallery $gallery): void {
            if ($gallery->sort_order === null) {
                $gallery->sort_order = (int) (static::query()->max('sort_order') ?? 0) + 1;
            }

            $gallery->parent_key = (int) ($gallery->parent_id ?? 0);
            $gallery->kind = $gallery->kind === self::KIND_GROUP ? self::KIND_GROUP : self::KIND_ALBUM;

            if ($gallery->parent_id !== null) {
                $parent = static::query()->find($gallery->parent_id);

                if ($parent !== null && $parent->isAlbum()) {
                    throw ValidationException::withMessages([
                        'parent_id' => [__('vmedia::admin.galleries.validation.album_cannot_be_parent')],
                    ]);
                }
            }

            if ($gallery->isGroup() && $gallery->exists) {
                $hasMedia = DB::table((string) config('vmedia.tables.gallery_media', 'voodbuilder_media_gallery_media'))
                    ->where('gallery_id', $gallery->getKey())
                    ->exists();

                if ($hasMedia) {
                    throw ValidationException::withMessages([
                        'kind' => [__('vmedia::admin.galleries.validation.group_cannot_hold_media')],
                    ]);
                }
            }

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
        if ($this->isGroup()) {
            throw ValidationException::withMessages([
                'gallery' => [__('vmedia::admin.galleries.validation.group_cannot_hold_media')],
            ]);
        }

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
        if ($this->isGroup()) {
            throw ValidationException::withMessages([
                'gallery' => [__('vmedia::admin.galleries.validation.group_cannot_hold_media')],
            ]);
        }

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
