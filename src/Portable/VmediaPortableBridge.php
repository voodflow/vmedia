<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Portable;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vportable\Contracts\MediaPortableBridge;
use Voodflow\Vportable\Support\ImportMode;
use Voodflow\Vportable\Support\ImportOptions;
use Voodflow\Vportable\Support\ImportReport;
use Voodflow\Vportable\Support\PackReader;
use Voodflow\Vportable\Support\PackWriter;

/**
 * Packs / restores vault media by stable uuid for any PortableModule references.
 */
final class VmediaPortableBridge implements MediaPortableBridge
{
    public function key(): string
    {
        return 'vmedia';
    }

    /**
     * @param  list<string>  $references
     */
    public function exportReferenced(array $references, PackWriter $writer): void
    {
        $uuids = array_values(array_unique(array_filter($references)));

        if ($uuids === []) {
            $writer->putJson('media/catalog.json', [
                'bridge' => $this->key(),
                'items' => [],
            ]);

            return;
        }

        $items = MediaItem::query()
            ->whereIn('uuid', $uuids)
            ->get();

        $catalog = [];

        foreach ($items as $media) {
            $path = $media->getPath();

            if (! is_string($path) || $path === '' || ! is_file($path)) {
                continue;
            }

            $relative = 'media/files/'.$media->uuid.'/'.basename($path);
            $writer->putFile($relative, $path);

            $catalog[] = [
                'uuid' => (string) $media->uuid,
                'name' => $media->displayTitle(),
                'file_name' => (string) $media->file_name,
                'mime_type' => (string) $media->mime_type,
                'collection_name' => (string) $media->collection_name,
                'caption' => $media->caption(),
                'alt' => $media->alt(),
                'credits' => $media->credits(),
                'custom_properties' => $media->custom_properties ?? [],
                'pack_path' => $relative,
            ];
        }

        $writer->putJson('media/catalog.json', [
            'bridge' => $this->key(),
            'items' => $catalog,
            'requested' => count($uuids),
            'exported' => count($catalog),
        ]);
    }

    public function importMedia(PackReader $reader, ImportOptions $options): ImportReport
    {
        $report = new ImportReport;

        $catalogPath = $reader->root().DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'catalog.json';

        if (! is_file($catalogPath)) {
            return $report;
        }

        /** @var array{items?: list<array<string, mixed>>} $catalog */
        $catalog = json_decode((string) File::get($catalogPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ($catalog['items'] ?? [] as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            if ($uuid === '') {
                $report->error('Media catalog row missing uuid');

                continue;
            }

            $existing = MediaItem::query()->where('uuid', $uuid)->first();

            if ($existing !== null) {
                if ($options->mode === ImportMode::Skip || $options->mode === ImportMode::Update) {
                    if ($options->mode === ImportMode::Update && ! $options->dryRun) {
                        $existing->setCaption(isset($row['caption']) ? (string) $row['caption'] : null);
                        $existing->setAlt(isset($row['alt']) ? (string) $row['alt'] : null);
                        $existing->setCredits(isset($row['credits']) ? (string) $row['credits'] : null);
                        $existing->save();
                        $report->updated++;
                    } else {
                        $report->skipped++;
                    }

                    continue;
                }

                // Duplicate: import as new uuid (cannot keep same uuid)
                if ($options->mode === ImportMode::Duplicate) {
                    $report->warning("Duplicate mode cannot preserve media uuid [{$uuid}]; skipping file reuse");
                    $report->skipped++;

                    continue;
                }
            }

            $packPath = (string) ($row['pack_path'] ?? '');
            $absolute = $packPath !== ''
                ? $reader->root().DIRECTORY_SEPARATOR.ltrim(str_replace(['\\', '..'], ['/', ''], $packPath), '/')
                : '';

            if ($absolute === '' || ! is_file($absolute)) {
                $report->error("Missing media file for uuid [{$uuid}]");

                continue;
            }

            if ($options->dryRun) {
                $report->created++;

                continue;
            }

            $uploaded = new UploadedFile(
                $absolute,
                (string) ($row['file_name'] ?? basename($absolute)),
                (string) ($row['mime_type'] ?? null),
                null,
                true,
            );

            $custom = is_array($row['custom_properties'] ?? null) ? $row['custom_properties'] : [];
            unset($custom['content_hash'], $custom['original_content_hash'], $custom['original_backup_path']);

            $media = MediaLibrary::store(
                $uploaded,
                MediaGallery::default(),
                isset($row['name']) ? (string) $row['name'] : null,
                isset($row['caption']) ? (string) $row['caption'] : null,
                $custom,
            );

            if ((string) $media->uuid !== $uuid) {
                $media->uuid = $uuid;
                $media->save();
            }

            $report->created++;
        }

        return $report;
    }

    /**
     * @return list<string>
     */
    public static function allVaultUuids(): array
    {
        return MediaItem::query()
            ->orderBy('id')
            ->pluck('uuid')
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->filter(static fn (string $uuid): bool => $uuid !== '')
            ->values()
            ->all();
    }
}
