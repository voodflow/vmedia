<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Console;

use Illuminate\Console\Command;
use Voodflow\Vmedia\Support\Integration\DuplicateLibraryAlbumPruner;
use Voodflow\Vmedia\Support\Integration\PluginVaultRootBootstrap;

final class EnsurePluginVaultRootsCommand extends Command
{
    protected $signature = 'vmedia:ensure-plugin-roots';

    protected $description = 'Create or sync top-level vmedia folders for voodflow plugins and the shared Logos library';

    public function handle(): int
    {
        PluginVaultRootBootstrap::ensureAll();

        $pruned = DuplicateLibraryAlbumPruner::prune();

        if ($pruned > 0) {
            $this->warn("Removed {$pruned} duplicate plugin Library album(s).");
        }

        $this->info('Plugin vault root folders are ready.');

        return self::SUCCESS;
    }
}
