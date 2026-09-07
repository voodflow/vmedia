<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery for hosts where `add_soft_deletes_to_media_table` ran before the Spatie
 * `media` table existed (early return) and was recorded as migrated — SoftDeletes
 * then queries a missing `deleted_at` column on /admin/vmedia/library.
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
        // Do not drop deleted_at — the original soft-deletes migration owns that.
    }
};
