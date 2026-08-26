<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Console;

use Illuminate\Console\Command;
use Voodflow\Vmedia\Support\MediaUsage;

class StatsCommand extends Command
{
    protected $signature = 'vmedia:stats';

    protected $description = 'Show vault media library statistics';

    public function handle(): int
    {
        $stats = MediaUsage::stats();

        $this->components->info('vmedia vault stats');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Media (active)', (string) $stats['media']],
                ['Images', (string) $stats['images']],
                ['Videos', (string) $stats['videos']],
                ['Files', (string) $stats['files']],
                ['Galleries', (string) $stats['galleries']],
                ['Bytes', number_format($stats['bytes'])],
                ['Orphans', (string) $stats['orphans']],
                ['Trashed', (string) $stats['trashed']],
            ],
        );

        return self::SUCCESS;
    }
}
