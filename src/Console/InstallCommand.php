<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'vmedia:install
                            {--force : Overwrite already published files}
                            {--skip-migrate : Publish without running migrate}';

    protected $description = 'Publish vmedia config/migrations (and Spatie media table if missing)';

    /** @var array<string, string> */
    protected array $publishTags = [
        'medialibrary-config' => 'spatie/laravel-medialibrary',
        'medialibrary-migrations' => 'spatie/laravel-medialibrary',
        'vmedia-config' => 'voodflow/vmedia',
    ];

    public function handle(): int
    {
        $this->components->info('Installing voodflow/vmedia...');

        $publishOptions = array_filter([
            '--force' => $this->option('force'),
        ]);

        foreach ($this->publishTags as $tag => $package) {
            if ($package !== 'voodflow/vmedia' && ! InstalledVersions::isInstalled($package)) {
                $this->components->warn("Skipping {$tag}: package {$package} is not installed.");

                continue;
            }

            if ($tag === 'medialibrary-migrations' && $this->mediaTableMigrationIsAvailable()) {
                $this->components->warn('Skipping medialibrary-migrations: already published.');

                continue;
            }

            $this->components->info("Publishing {$tag}...");

            if ($this->call('vendor:publish', ['--tag' => $tag, ...$publishOptions]) !== self::SUCCESS) {
                $this->components->error("Failed to publish {$tag}.");

                return self::FAILURE;
            }
        }

        if ($this->option('skip-migrate')) {
            $this->components->info('Skipped migrations (--skip-migrate).');
            $this->printNextSteps();

            return self::SUCCESS;
        }

        $this->components->info('Running migrations...');

        if ($this->call('migrate', array_filter([
            '--force' => ! $this->input->isInteractive(),
        ])) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->assertMediaSoftDeletesReady();

        $this->components->success('voodflow/vmedia installed.');
        $this->printNextSteps();

        return self::SUCCESS;
    }

    protected function assertMediaSoftDeletesReady(): void
    {
        if (! Schema::hasTable('media')) {
            $this->components->warn('`media` table is still missing. Publish Spatie medialibrary migrations and re-run migrate.');

            return;
        }

        if (! Schema::hasColumn('media', 'deleted_at')) {
            $this->components->error('`media.deleted_at` is missing after migrate. Re-run `php artisan migrate` (vmedia ensure soft-deletes migration).');
        }
    }

    protected function mediaTableMigrationIsAvailable(): bool
    {
        foreach (glob(database_path('migrations/*create_media_table.php')) ?: [] as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return false;
    }

    protected function printNextSteps(): void
    {
        $this->newLine();
        $this->line('  Register on your Filament panel:');
        $this->line('    ->plugins([');
        $this->line('        \\Voodflow\\Vmedia\\VmediaPlugin::make(),');
        $this->line('    ])');
        $this->newLine();
    }
}
