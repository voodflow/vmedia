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
                (array) config('vmedia.upload.allowed_file_mimes', []),
            ),
        );

        if ($mime === '' || ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported media type.'],
            ]);
        }
    }

    /**
     * Clean an uploaded SVG by rewriting the temporary file, so the copy that reaches the
     * disk is the sanitized one.
     *
     * MIME allow-listing does not help here: `image/svg+xml` is a type we want to accept,
     * and the danger is in the document body rather than the type. Callers run this before
     * hashing so deduplication keys the bytes actually stored.
     */
    public static function sanitizeSvgInPlace(?UploadedFile $file): void
    {
        if ($file === null || ! self::looksLikeSvg($file)) {
            return;
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return;
        }

        $sanitized = SvgSanitizer::sanitize((string) file_get_contents($path));

        // Unparseable input is not something to put on a public disk — and an SVG that the
        // sanitizer cannot read is not one a browser should be asked to interpret either.
        if ($sanitized === '') {
            throw ValidationException::withMessages([
                'file' => ['The SVG could not be parsed and was rejected.'],
            ]);
        }

        file_put_contents($path, $sanitized);
    }

    private static function looksLikeSvg(UploadedFile $file): bool
    {
        return strtolower((string) $file->getClientOriginalExtension()) === 'svg'
            || str_contains(strtolower((string) $file->getMimeType()), 'svg');
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
