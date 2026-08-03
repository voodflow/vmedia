<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Upload safety checks (MIME allow-list, path traversal in client names).
 */
final class UploadGuard
{
    public static function assertSafeUpload(?UploadedFile $file): void
    {
        if ($file === null) {
            throw ValidationException::withMessages([
                'file' => ['A file is required.'],
            ]);
        }

        $original = (string) $file->getClientOriginalName();

        if (self::containsPathTraversal($original)) {
            throw ValidationException::withMessages([
                'file' => ['Invalid file name.'],
            ]);
        }
    }

    public static function assertAllowedMime(UploadedFile $file): void
    {
        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        $allowed = array_map(
            'strtolower',
            array_merge(
                (array) config('vmedia.upload.allowed_image_mimes', []),
                (array) config('vmedia.upload.allowed_video_mimes', []),
            ),
        );

        if ($mime === '' || ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported media type.'],
            ]);
        }
    }

    public static function containsPathTraversal(string $value): bool
    {
        $normalized = str_replace('\\', '/', $value);

        if (str_contains($normalized, "\0")) {
            return true;
        }

        if (str_contains($normalized, '../') || str_contains($normalized, '..\\')) {
            return true;
        }

        return str_starts_with($normalized, '/') || preg_match('#^[a-zA-Z]:/#', $normalized) === 1;
    }

    /**
     * Ensure a relative storage path cannot escape the disk root.
     */
    public static function assertSafeRelativePath(string $relative): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relative), '/');

        if ($normalized === '' || self::containsPathTraversal($normalized) || str_contains($normalized, '..')) {
            throw new \InvalidArgumentException('Invalid media path.');
        }

        return $normalized;
    }
}
