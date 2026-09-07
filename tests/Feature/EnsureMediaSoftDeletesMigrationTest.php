<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Voodflow\Vmedia\Tests\TestCase;

class EnsureMediaSoftDeletesMigrationTest extends TestCase
{
    public function test_ensure_migration_adds_deleted_at_when_missing(): void
    {
        Schema::table('media', function ($table): void {
            if (Schema::hasColumn('media', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        $this->assertFalse(Schema::hasColumn('media', 'deleted_at'));

        $migration = require __DIR__.'/../../database/migrations/2026_09_07_160000_ensure_media_soft_deletes_column.php';
        $migration->up();

        $this->assertTrue(Schema::hasColumn('media', 'deleted_at'));
    }
}
