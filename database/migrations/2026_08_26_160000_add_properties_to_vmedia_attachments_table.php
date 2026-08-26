<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('vmedia.tables.attachments', 'vmedia_attachments');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'properties')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('properties')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        $table = (string) config('vmedia.tables.attachments', 'vmedia_attachments');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'properties')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('properties');
        });
    }
};
