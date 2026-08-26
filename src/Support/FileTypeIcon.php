<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Voodflow\Vmedia\Models\MediaItem;

/**
 * Resolve a visual file-type key + label for vault attachments (admin thumbs).
 */
final class FileTypeIcon
{
    public const FALLBACK = 'file';

    /**
     * @return array{icon: string, icon_label: string}
     */
    public static function forMedia(MediaItem|Media $media): array
    {
        $mime = strtolower((string) ($media->mime_type ?? ''));
        $fileName = (string) ($media->file_name ?? $media->name ?? '');
        $assetType = $media instanceof MediaItem
            ? MediaLibrary::assetType($media)
            : null;

        return self::resolve($mime, $fileName, $assetType);
    }

    /**
     * @return array{icon: string, icon_label: string}
     */
    public static function resolve(?string $mime, ?string $fileName, ?string $assetType = null): array
    {
        $key = self::key($mime, $fileName, $assetType);

        return [
            'icon' => $key,
            'icon_label' => self::label($key, $fileName),
        ];
    }

    public static function key(?string $mime, ?string $fileName, ?string $assetType = null): string
    {
        $extension = self::extension($fileName);
        $mime = strtolower(trim((string) $mime));

        $fromExtension = match ($extension) {
            'pdf' => 'pdf',
            'zip' => 'zip',
            'doc', 'docx' => 'doc',
            'xls', 'xlsx' => 'xls',
            'csv' => 'csv',
            'txt' => 'txt',
            'mp4', 'webm', 'ogg', 'mov', 'm4v' => 'video',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif' => 'image',
            default => null,
        };

        if ($fromExtension !== null) {
            return $fromExtension;
        }

        $fromMime = match (true) {
            $mime === 'application/pdf' => 'pdf',
            in_array($mime, ['application/zip', 'application/x-zip-compressed'], true) => 'zip',
            in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true) => 'doc',
            in_array($mime, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true) => 'xls',
            $mime === 'text/csv' => 'csv',
            $mime === 'text/plain' => 'txt',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'image/') => 'image',
            default => null,
        };

        if ($fromMime !== null) {
            return $fromMime;
        }

        return match ($assetType) {
            'video' => 'video',
            'image' => 'image',
            default => self::FALLBACK,
        };
    }

    public static function label(string $key, ?string $fileName = null): string
    {
        $extension = self::extension($fileName);

        if ($extension !== '') {
            return strtoupper($extension);
        }

        return match ($key) {
            'pdf' => 'PDF',
            'zip' => 'ZIP',
            'doc' => 'DOC',
            'xls' => 'XLS',
            'csv' => 'CSV',
            'txt' => 'TXT',
            'video' => 'VIDEO',
            'image' => 'IMG',
            default => 'FILE',
        };
    }

    public static function dataUriFor(MediaItem|Media $media): string
    {
        $meta = self::forMedia($media);

        return self::dataUri($meta['icon'], $meta['icon_label']);
    }

    public static function dataUri(string $icon, string $label): string
    {
        $color = match ($icon) {
            'pdf' => '#dc2626',
            'zip' => '#d97706',
            'doc' => '#2563eb',
            'xls' => '#059669',
            'csv' => '#0d9488',
            'txt' => '#6b7280',
            'video' => '#7c3aed',
            'image' => '#0891b2',
            default => '#64748b',
        };

        $safeLabel = htmlspecialchars(mb_substr($label, 0, 6), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $bg = htmlspecialchars($color, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64" role="img">
  <rect width="64" height="64" rx="10" fill="{$bg}" fill-opacity="0.12"/>
  <path d="M22 12h16l8 8v28a4 4 0 0 1-4 4H22a4 4 0 0 1-4-4V16a4 4 0 0 1 4-4z" fill="#fff" stroke="{$bg}" stroke-width="1.5"/>
  <path d="M38 12v8h8" fill="none" stroke="{$bg}" stroke-width="1.5"/>
  <rect x="16" y="42" width="32" height="12" rx="3" fill="{$bg}"/>
  <text x="32" y="51" text-anchor="middle" font-family="ui-sans-serif,system-ui,sans-serif" font-size="8" font-weight="700" fill="#fff">{$safeLabel}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function extension(?string $fileName): string
    {
        if ($fileName === null || $fileName === '') {
            return '';
        }

        return strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
    }
}
