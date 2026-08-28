<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
        $table = (string) config('vmedia.tables.galleries', 'voodbuilder_media_galleries');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'parent_id')) {
                $blueprint->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained($table)
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn($table, 'parent_key')) {
                $blueprint->unsignedBigInteger('parent_key')->default(0)->after('parent_id');
            }

            if (! Schema::hasColumn($table, 'kind')) {
                $blueprint->string('kind', 16)->default('album')->after('slug');
            }

            if (! Schema::hasColumn($table, 'integration_source')) {
                $blueprint->string('integration_source', 64)->nullable()->after('sort_order');
            }

            if (! Schema::hasColumn($table, 'integration_key')) {
                $blueprint->string('integration_key', 191)->nullable()->after('integration_source');
            }
        });

        DB::table($table)->whereNull('parent_id')->update(['parent_key' => 0]);

        if ($this->hasIndexNamed($table, 'voodbuilder_media_galleries_slug_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['slug']);
            });
        }

        if (! $this->hasIndexNamed($table, 'vmedia_galleries_parent_key_slug_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique(['parent_key', 'slug'], 'vmedia_galleries_parent_key_slug_unique');
            });
        }

        if (! $this->hasIndexNamed($table, 'vmedia_galleries_integration_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique(['integration_source', 'integration_key'], 'vmedia_galleries_integration_unique');
            });
        }

        DB::table($table)->where(function ($query): void {
            $query->whereNull('kind')->orWhere('kind', '');
        })->update(['kind' => 'album']);
    }

    public function down(): void
    {
        $table = (string) config('vmedia.tables.galleries', 'voodbuilder_media_galleries');

        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->hasIndexNamed($table, 'vmedia_galleries_integration_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique('vmedia_galleries_integration_unique');
            });
        }

        if ($this->hasIndexNamed($table, 'vmedia_galleries_parent_key_slug_unique')) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique('vmedia_galleries_parent_key_slug_unique');
            });
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'parent_id')) {
                $blueprint->dropConstrainedForeignId('parent_id');
            }

            foreach (['parent_key', 'kind', 'integration_source', 'integration_key'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }

            if (! $this->hasIndexNamed($table, 'voodbuilder_media_galleries_slug_unique')) {
                $blueprint->unique('slug');
            }
        });
    }

    protected function hasIndexNamed(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName],
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }
};
