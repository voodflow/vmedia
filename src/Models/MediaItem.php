<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Spatie Media row with gallery memberships (many-to-many).
 *
 * Naming:
 * - `name` — human-readable display title (original client filename stem or custom title)
 * - `file_name` — storage filename on disk (usually a hash from Spatie)
 * - `custom_properties.caption` — optional library caption (fallback for page/block captions)
 */
class MediaItem extends Media
{
    public const CUSTOM_CAPTION = 'caption';

    /**
     * @return BelongsToMany<MediaGallery, $this>
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaGallery::class,
            (string) config('vmedia.tables.gallery_media', 'voodbuilder_media_gallery_media'),
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
            ? (string) __('vmedia::admin.library.video')
            : (string) __('vmedia::admin.library.photo');
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
        $caption = $this->getCustomProperty(self::CUSTOM_CAPTION);

        if (! is_string($caption)) {
            return null;
        }

        $caption = trim($caption);

        return $caption !== '' ? $caption : null;
    }

    public function setCaption(?string $caption): static
    {
        $trimmed = is_string($caption) ? trim($caption) : '';

        if ($trimmed === '') {
            $this->forgetCustomProperty(self::CUSTOM_CAPTION);
        } else {
            $this->setCustomProperty(self::CUSTOM_CAPTION, $trimmed);
        }

        return $this;
    }
}
