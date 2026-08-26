<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Voodflow\Vmedia\Models\MediaAttachment;
use Voodflow\Vmedia\Models\MediaItem;

/**
 * Resolve caption / alt / credits with attachment override → vault default.
 */
final class AttachmentMeta
{
    /**
     * @return array{caption: string|null, alt: string|null, credits: string|null}
     */
    public static function fromMedia(MediaItem $media): array
    {
        return [
            'caption' => self::caption($media),
            'alt' => self::alt($media),
            'credits' => self::credits($media),
        ];
    }

    /**
     * Raw pivot overrides only (may be empty).
     *
     * @return array{caption?: string, alt?: string, credits?: string}
     */
    public static function overrides(MediaItem $media): array
    {
        $pivot = $media->pivot;

        if (! $pivot instanceof MediaAttachment) {
            return [];
        }

        $out = [];

        foreach ([MediaItem::CUSTOM_CAPTION, MediaItem::CUSTOM_ALT, MediaItem::CUSTOM_CREDITS] as $key) {
            $value = $pivot->property($key);

            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function caption(MediaItem $media): ?string
    {
        $pivot = $media->pivot;

        if ($pivot instanceof MediaAttachment && $pivot->caption() !== null) {
            return $pivot->caption();
        }

        return $media->caption();
    }

    public static function alt(MediaItem $media): ?string
    {
        $pivot = $media->pivot;

        if ($pivot instanceof MediaAttachment && $pivot->alt() !== null) {
            return $pivot->alt();
        }

        return $media->alt();
    }

    public static function credits(MediaItem $media): ?string
    {
        $pivot = $media->pivot;

        if ($pivot instanceof MediaAttachment && $pivot->credits() !== null) {
            return $pivot->credits();
        }

        return $media->credits();
    }

    /**
     * Normalize form/state bag into pivot properties (empty strings → omitted).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalizeProperties(array $input): array
    {
        $out = [];

        foreach ([MediaItem::CUSTOM_CAPTION, MediaItem::CUSTOM_ALT, MediaItem::CUSTOM_CREDITS] as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if ($value === null) {
                continue;
            }

            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                $out[$key] = $trimmed;
            }
        }

        return $out;
    }
}
