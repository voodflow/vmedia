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
 *
 * Security: path traversal blocked, extension+MIME whitelist, max entries,
 * max per-entry size, max total uncompressed size. Disallowed entries are skipped
 * (not only images — also videos/docs from config). Nested ZIPs are not extracted.
 */
final class ZipImporter
{
    /**
     * @param  iterable<int|MediaGallery>|MediaGallery|null  $galleries
     * @return array{items: list<MediaItem>, skipped: int, imported: int}
     */
    public static function import(UploadedFile|string $zip, iterable|MediaGallery|null $galleries = null): array
    {
        $path = $zip instanceof UploadedFile ? $zip->getRealPath() : $zip;

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw ValidationException::withMessages([
                'zip' => ['ZIP file not found.'],
            ]);
        }

        $maxArchiveKb = max(1, (int) config('vmedia.zip.max_archive_kb', 51200));
        $archiveSize = (int) filesize($path);

        if ($archiveSize > $maxArchiveKb * 1024) {
            throw ValidationException::withMessages([
                'zip' => ["ZIP exceeds {$maxArchiveKb} KB limit."],
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
        $skipped = 0;

        try {
            $maxFiles = max(1, (int) config('vmedia.zip.max_files', 100));
            $maxEntryKb = max(1, (int) config('vmedia.zip.max_entry_kb', 20480));
            $maxTotalKb = max(1, (int) config('vmedia.zip.max_total_uncompressed_kb', 102400));
            $maxEntryBytes = $maxEntryKb * 1024;
            $maxTotalBytes = $maxTotalKb * 1024;
            $totalUncompressed = 0;
            $extracted = 0;
            $allowed = array_map('strtolower', (array) config('vmedia.upload.allowed_extensions', []));

            for ($i = 0; $i < $archive->numFiles; $i++) {
                if ($extracted >= $maxFiles) {
                    $skipped += max(0, $archive->numFiles - $i);
                    break;
                }

                $stat = $archive->statIndex($i);
                $name = is_array($stat) ? ($stat['name'] ?? null) : $archive->getNameIndex($i);

                if (! is_string($name) || $name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if (UploadGuard::containsPathTraversal($name) || str_contains($name, '..')) {
                    $skipped++;

                    continue;
                }

                $basename = basename(str_replace('\\', '/', $name));
                $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));

                // Nested archives are never extracted (zip-slip / nested bomb surface).
                if ($extension === 'zip' || $extension === '' || ! in_array($extension, $allowed, true)) {
                    $skipped++;

                    continue;
                }

                $entrySize = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;

                if ($entrySize > $maxEntryBytes) {
                    $skipped++;

                    continue;
                }

                if (($totalUncompressed + $entrySize) > $maxTotalBytes) {
                    $skipped++;

                    continue;
                }

                $stream = $archive->getStream($name);

                if ($stream === false) {
                    $skipped++;

                    continue;
                }

                $target = $tempDir.DIRECTORY_SEPARATOR.$extracted.'_'.$basename;
                $contents = stream_get_contents($stream);
                fclose($stream);

                if ($contents === false) {
                    $skipped++;

                    continue;
                }

                $written = strlen($contents);

                if ($written > $maxEntryBytes || ($totalUncompressed + $written) > $maxTotalBytes) {
                    $skipped++;

                    continue;
                }

                file_put_contents($target, $contents);
                $totalUncompressed += $written;

                $mime = mime_content_type($target) ?: null;
                $uploaded = new UploadedFile($target, $basename, $mime, null, true);

                try {
                    UploadGuard::assertAllowedMime($uploaded);
                    $stored[] = MediaLibrary::store($uploaded, $galleries);
                    $extracted++;
                } catch (ValidationException) {
                    $skipped++;
                }
            }
        } finally {
            $archive->close();
            File::deleteDirectory($tempDir);
        }

        return [
            'items' => $stored,
            'imported' => count($stored),
            'skipped' => $skipped,
        ];
    }
}
