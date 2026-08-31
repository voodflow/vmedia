<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Spatie Media row with gallery memberships (many-to-many).
 *
 * Naming:
 * - `name` — human-readable display title (original client filename stem or custom title)
 * - `file_name` — storage filename on disk (usually a hash from Spatie)
 * - `custom_properties.caption` — optional library caption
 * - `custom_properties.alt` — accessibility alternative text
 * - `custom_properties.credits` — optional attribution
 * - `custom_properties.focal_x` / `focal_y` — 0–100 focus point for CSS object-position
 * - `custom_properties.poster_uuid` — vault image used as video poster
 * - `custom_properties.content_hash` — sha256 for duplicate detection
 */
class MediaItem extends Media
{
    use SoftDeletes;

    public const CUSTOM_CAPTION = 'caption';

    public const CUSTOM_ALT = 'alt';

    public const CUSTOM_CREDITS = 'credits';

    public const CUSTOM_FOCAL_X = 'focal_x';

    public const CUSTOM_FOCAL_Y = 'focal_y';

    public const CUSTOM_POSTER_UUID = 'poster_uuid';

    public const CUSTOM_CONTENT_HASH = 'content_hash';

    public const CUSTOM_ORIGINAL_CONTENT_HASH = 'original_content_hash';

    public const CUSTOM_ORIGINAL_BACKUP_PATH = 'original_backup_path';

    public const CUSTOM_DERIVED_FROM_UUID = 'derived_from_uuid';

    public const CUSTOM_EDITED_AT = 'edited_at';

    /**
     * @return BelongsToMany<MediaGallery, $this>
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaGallery::class,
            (string) config('vmedia.tables.gallery_media', 'vmedia_gallery_media'),
            'media_id',
            'gallery_id',
        )
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<MediaTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaTag::class,
            (string) config('vmedia.tables.media_tags', 'vmedia_media_tags'),
            'media_id',
            'tag_id',
        )->withTimestamps();
    }

    public function isVideo(): bool
    {
        return $this->collection_name === MediaGallery::COLLECTION_VIDEOS
            || str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isFile(): bool
    {
        return $this->collection_name === MediaGallery::COLLECTION_FILES
            || (! $this->isVideo() && ! str_starts_with((string) $this->mime_type, 'image/'));
    }

    public function isImage(): bool
    {
        return ! $this->isVideo() && ! $this->isFile();
    }

    public function kindLabel(): string
    {
        if ($this->isVideo()) {
            return (string) __('vmedia::admin.library.video');
        }

        if ($this->isFile()) {
            return (string) __('vmedia::admin.library.file');
        }

        return (string) __('vmedia::admin.library.photo');
    }

    /**
     * Stable human-readable title shown in admin / editor.
     */
    public function displayTitle(): string
    {
        $name = trim((string) $this->name);

        if ($name !== '') {
            return $name;
        }

        $file = trim((string) $this->file_name);

        return $file !== ''
            ? (string) pathinfo($file, PATHINFO_FILENAME)
            : 'media';
    }

    public function caption(): ?string
    {
        return $this->stringCustomProperty(self::CUSTOM_CAPTION);
    }

    public function setCaption(?string $caption): static
    {
        return $this->setOptionalStringProperty(self::CUSTOM_CAPTION, $caption);
    }

    public function alt(): ?string
    {
        return $this->stringCustomProperty(self::CUSTOM_ALT);
    }

    public function setAlt(?string $alt): static
    {
        return $this->setOptionalStringProperty(self::CUSTOM_ALT, $alt);
    }

    public function credits(): ?string
    {
        return $this->stringCustomProperty(self::CUSTOM_CREDITS);
    }

    public function setCredits(?string $credits): static
    {
        return $this->setOptionalStringProperty(self::CUSTOM_CREDITS, $credits);
    }

    public function focalX(): ?float
    {
        return $this->floatCustomProperty(self::CUSTOM_FOCAL_X);
    }

    public function focalY(): ?float
    {
        return $this->floatCustomProperty(self::CUSTOM_FOCAL_Y);
    }

    public function setFocalPoint(?float $x, ?float $y): static
    {
        if ($x === null) {
            $this->forgetCustomProperty(self::CUSTOM_FOCAL_X);
        } else {
            $this->setCustomProperty(self::CUSTOM_FOCAL_X, max(0, min(100, $x)));
        }

        if ($y === null) {
            $this->forgetCustomProperty(self::CUSTOM_FOCAL_Y);
        } else {
            $this->setCustomProperty(self::CUSTOM_FOCAL_Y, max(0, min(100, $y)));
        }

        return $this;
    }

    public function posterUuid(): ?string
    {
        return $this->stringCustomProperty(self::CUSTOM_POSTER_UUID);
    }

    public function setPosterUuid(?string $uuid): static
    {
        return $this->setOptionalStringProperty(self::CUSTOM_POSTER_UUID, $uuid);
    }

    public function contentHash(): ?string
    {
        return $this->stringCustomProperty(self::CUSTOM_CONTENT_HASH);
    }

    public function objectPositionCss(): ?string
    {
        $x = $this->focalX();
        $y = $this->focalY();

        if ($x === null && $y === null) {
            return null;
        }

        return ($x ?? 50).'% '.($y ?? 50).'%';
    }

    protected function stringCustomProperty(string $key): ?string
    {
        $value = $this->getCustomProperty($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function floatCustomProperty(string $key): ?float
    {
        $value = $this->getCustomProperty($key);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function setOptionalStringProperty(string $key, ?string $value): static
    {
        $trimmed = is_string($value) ? trim($value) : '';

        if ($trimmed === '') {
            $this->forgetCustomProperty($key);
        } else {
            $this->setCustomProperty($key, $trimmed);
        }

        return $this;
    }
}
