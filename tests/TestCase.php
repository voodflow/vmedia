<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Voodflow\Vmedia\Tests\Concerns\InteractsWithVmediaTests;

if (class_exists(\Tests\TestCase::class)) {
    /**
     * Host application base (php artisan test from Cosmolab root).
     */
    abstract class TestCase extends \Tests\TestCase
    {
        use InteractsWithVmediaTests;

        protected function setUp(): void
        {
            parent::setUp();

            $this->refreshVmediaTestDatabase();
            $this->bootVmediaTests();
        }

        protected function refreshVmediaTestDatabase(): void
        {
            Schema::disableForeignKeyConstraints();
            Schema::dropAllTables();
            Schema::enableForeignKeyConstraints();

            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });

            Schema::create('media', function (Blueprint $table): void {
                $table->id();
                $table->morphs('model');
                $table->uuid()->nullable()->unique();
                $table->string('collection_name');
                $table->string('name');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->string('disk');
                $table->string('conversions_disk')->nullable();
                $table->unsignedBigInteger('size');
                $table->json('manipulations');
                $table->json('custom_properties');
                $table->json('generated_conversions');
                $table->json('responsive_images');
                $table->unsignedInteger('order_column')->nullable()->index();
                $table->nullableTimestamps();
                $table->softDeletes();
            });

            $migrationPath = dirname(__DIR__).'/database/migrations';
            $files = [
                '2026_07_31_160000_create_voodbuilder_media_galleries_table.php',
                '2026_07_31_170000_create_voodbuilder_media_vault_and_gallery_pivot.php',
                '2026_08_26_120000_create_vmedia_attachments_table.php',
                '2026_08_26_160000_add_properties_to_vmedia_attachments_table.php',
                '2026_08_28_100000_add_hierarchy_to_vmedia_galleries.php',
                '2026_08_28_100001_create_vmedia_tags_tables.php',
            ];

            foreach ($files as $file) {
                $this->artisan('migrate', [
                    '--path' => $migrationPath.'/'.$file,
                    '--realpath' => true,
                    '--force' => true,
                ]);
            }
        }
    }
} else {
    abstract class TestCase extends OrchestraTestCase {}
}
