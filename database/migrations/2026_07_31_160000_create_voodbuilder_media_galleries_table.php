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
        $table = (string) config('voodbuilder-media.tables.galleries', 'voodbuilder_media_galleries');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('slug')->unique();
                $blueprint->text('description')->nullable();
                $blueprint->boolean('is_default')->default(false)->index();
                $blueprint->boolean('is_public')->default(true);
                $blueprint->unsignedInteger('sort_order')->default(0);
                $blueprint->timestamps();
            });
        }

        $this->seedDefaultGallery($table);
        $this->migrateLegacyLibraryMedia($table);
    }

    public function down(): void
    {
        $table = (string) config('voodbuilder-media.tables.galleries', 'voodbuilder_media_galleries');
        Schema::dropIfExists($table);
    }

    protected function seedDefaultGallery(string $table): void
    {
        if (DB::table($table)->where('is_default', true)->exists()) {
            return;
        }

        if (DB::table($table)->exists()) {
            DB::table($table)->orderBy('id')->limit(1)->update(['is_default' => true]);

            return;
        }

        $now = now();

        DB::table($table)->insert([
            'name' => 'Library',
            'slug' => 'library',
            'description' => 'Default shared media library',
            'is_default' => true,
            'is_public' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Move media from Core singleton voodbuilder_media_libraries → default gallery.
     */
    protected function migrateLegacyLibraryMedia(string $galleriesTable): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasTable('voodbuilder_media_libraries')) {
            return;
        }

        $galleryId = DB::table($galleriesTable)->where('is_default', true)->value('id')
            ?? DB::table($galleriesTable)->orderBy('id')->value('id');

        if ($galleryId === null) {
            return;
        }

        $legacyIds = DB::table('voodbuilder_media_libraries')->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        DB::table('media')
            ->where('model_type', 'voodbuilder_media_library')
            ->whereIn('model_id', $legacyIds)
            ->update([
                'model_type' => 'voodbuilder_media_gallery',
                'model_id' => $galleryId,
            ]);
    }
};
