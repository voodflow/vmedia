<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Console;

use Illuminate\Console\Command;
use Voodflow\Vmedia\Events\MediaDeleted;
use Voodflow\Vmedia\Support\MediaUsage;

class PruneOrphansCommand extends Command
{
    protected $signature = 'vmedia:prune-orphans
                            {--days=30 : Only prune orphans older than this many days}
                            {--force : Actually delete (soft-delete unless --hard)}
                            {--hard : Force-delete files from disk}';

    protected $description = 'Prune vault media with no gallery membership and no attachments';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $query = MediaUsage::orphanQuery();

        if ($days > 0) {
            $query->where('created_at', '<=', now()->subDays($days));
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->components->info('No orphan media to prune.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->components->warn("Found {$count} orphan(s). Re-run with --force to delete.");

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->orderBy('id')->chunkById(50, function ($items) use (&$deleted): void {
            foreach ($items as $media) {
                if ($this->option('hard')) {
                    $media->forceDelete();
                    MediaDeleted::dispatch($media, true);
                } else {
                    $media->delete();
                    MediaDeleted::dispatch($media, false);
                }

                $deleted++;
            }
        });

        $this->components->success("Pruned {$deleted} orphan media item(s).");

        return self::SUCCESS;
    }
}
