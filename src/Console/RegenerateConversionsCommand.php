<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Console;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;
use Voodflow\Vmedia\Support\ConversionLadder;

class RegenerateConversionsCommand extends Command
{
    protected $signature = 'vmedia:regenerate-conversions
                            {--force : Skip confirmation}
                            {--only=* : Limit to conversion keys (repeatable)}';

    protected $description = 'Regenerate Spatie image conversions for all vault images (after ladder changes)';

    public function handle(FileManipulator $fileManipulator): int
    {
        if (! (bool) config('vmedia.conversions.enabled', true)) {
            $this->components->warn('Conversions are disabled (VMEDIA_CONVERSIONS=false).');

            return self::SUCCESS;
        }

        $keys = [];

        foreach ((array) $this->option('only') as $key) {
            if (! is_string($key)) {
                continue;
            }

            $sanitized = ConversionLadder::sanitizeKey($key);

            if ($sanitized !== '') {
                $keys[] = $sanitized;
            }
        }

        $keys = array_values(array_unique($keys));

        $this->components->info(
            'Conversion keys: '.($keys === [] ? implode(', ', ConversionLadder::keys()) : implode(', ', $keys)),
        );

        if (! $this->option('force') && ! $this->confirm('Regenerate conversions for all vault images?', true)) {
            return self::SUCCESS;
        }

        $vault = MediaVault::current();
        $query = MediaItem::query()
            ->where('model_type', $vault->getMorphClass())
            ->where('model_id', $vault->getKey())
            ->where('collection_name', MediaGallery::COLLECTION_IMAGES);

        $total = (clone $query)->count();
        $done = 0;
        $failed = 0;

        $this->components->info("Processing {$total} image(s)…");

        $query->orderBy('id')->chunkById(25, function ($items) use (&$done, &$failed, $keys, $fileManipulator): void {
            foreach ($items as $media) {
                /** @var MediaItem $media */
                try {
                    $fileManipulator->createDerivedFiles($media, $keys);
                    $done++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->components->warn("Media #{$media->getKey()}: {$exception->getMessage()}");
                }
            }
        });

        $this->components->success("Done. Regenerated: {$done}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
