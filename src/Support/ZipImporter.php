<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use ZipArchive;

/**
 * Extract allowed files from a ZIP into the media vault.
 */
final class ZipImporter
{
    /**
     * @param  iterable<int|MediaGallery>|MediaGallery|null  $galleries
     * @return list<MediaItem>
     */
    public static function import(UploadedFile|string $zip, iterable|MediaGallery|null $galleries = null): array
    {
        $path = $zip instanceof UploadedFile ? $zip->getRealPath() : $zip;

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw ValidationException::withMessages([
                'zip' => ['ZIP file not found.'],
            ]);
        }

        if ($zip instanceof UploadedFile) {
            UploadGuard::assertSafeUpload($zip);
            $clientName = strtolower((string) $zip->getClientOriginalName());

            if (! str_ends_with($clientName, '.zip')) {
                throw ValidationException::withMessages([
                    'zip' => ['Only .zip archives are allowed.'],
                ]);
            }
        }

        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw ValidationException::withMessages([
                'zip' => ['Unable to open ZIP archive.'],
            ]);
        }

        $tempDir = storage_path('app/vmedia-zip-'.Str::random(12));
        File::ensureDirectoryExists($tempDir);

        $stored = [];

        try {
            $maxFiles = (int) config('vmedia.zip.max_files', 100);
            $extracted = 0;

            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = $archive->getNameIndex($i);

                if (! is_string($name) || $name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if (UploadGuard::containsPathTraversal($name) || str_contains($name, '..')) {
                    continue;
                }

                $basename = basename($name);
                $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
                $allowed = array_map('strtolower', (array) config('vmedia.upload.allowed_extensions', []));

                if ($extension === '' || ! in_array($extension, $allowed, true)) {
                    continue;
                }

                if ($extracted >= $maxFiles) {
                    break;
                }

                $target = $tempDir.DIRECTORY_SEPARATOR.$basename;

                if (! $archive->extractTo($tempDir, $name)) {
                    continue;
                }

                $extractedPath = $tempDir.DIRECTORY_SEPARATOR.str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name);

                if (! is_file($extractedPath)) {
                    continue;
                }

                // Flatten nested paths into temp root for UploadedFile naming.
                if ($extractedPath !== $target) {
                    File::move($extractedPath, $target);
                }

                $mime = mime_content_type($target) ?: null;
                $uploaded = new UploadedFile($target, $basename, $mime, null, true);

                try {
                    UploadGuard::assertAllowedMime($uploaded);
                    $stored[] = MediaLibrary::store($uploaded, $galleries);
                    $extracted++;
                } catch (ValidationException) {
                    // Skip disallowed entries inside the archive.
                }
            }
        } finally {
            $archive->close();
            File::deleteDirectory($tempDir);
        }

        return $stored;
    }
}
