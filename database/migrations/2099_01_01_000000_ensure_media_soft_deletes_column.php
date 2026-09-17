<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runs after Spatie `create_media_table` published with a "today" timestamp.
 * Earlier soft-deletes migrations (2026_08_26 / 2026_09_07) often no-op first
 * and are recorded as migrated before the media table exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        if (Schema::hasColumn('media', 'deleted_at')) {
            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // Keep deleted_at — owned by the original soft-deletes migration.
    }
};
