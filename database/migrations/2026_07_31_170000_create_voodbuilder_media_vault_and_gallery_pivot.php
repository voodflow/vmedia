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
        $vaults = (string) config('voodbuilder-media.tables.vaults', 'voodbuilder_media_vaults');
        $pivot = (string) config('voodbuilder-media.tables.gallery_media', 'voodbuilder_media_gallery_media');
        $galleries = (string) config('voodbuilder-media.tables.galleries', 'voodbuilder_media_galleries');

        if (! Schema::hasTable($vaults)) {
            Schema::create($vaults, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($pivot)) {
            Schema::create($pivot, function (Blueprint $table) use ($galleries): void {
                $table->id();
                $table->foreignId('gallery_id')->constrained($galleries)->cascadeOnDelete();
                $table->unsignedBigInteger('media_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['gallery_id', 'media_id']);
                $table->index('media_id');
            });
        }

        $vaultId = $this->ensureVault($vaults);
        $this->migrateGalleryOwnedMediaToVaultAndPivot($vaultId, $pivot);
    }

    public function down(): void
    {
        $pivot = (string) config('voodbuilder-media.tables.gallery_media', 'voodbuilder_media_gallery_media');
        $vaults = (string) config('voodbuilder-media.tables.vaults', 'voodbuilder_media_vaults');

        Schema::dropIfExists($pivot);
        Schema::dropIfExists($vaults);
    }

    protected function ensureVault(string $vaults): int
    {
        $id = DB::table($vaults)->orderBy('id')->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $now = now();

        return (int) DB::table($vaults)->insertGetId([
            'name' => 'Media vault',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Move Spatie ownership to the vault and seed gallery memberships from previous model_id.
     */
    protected function migrateGalleryOwnedMediaToVaultAndPivot(int $vaultId, string $pivot): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $rows = DB::table('media')
            ->where('model_type', 'voodbuilder_media_gallery')
            ->get(['id', 'model_id']);

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($rows as $row) {
            $exists = DB::table($pivot)
                ->where('gallery_id', $row->model_id)
                ->where('media_id', $row->id)
                ->exists();

            if (! $exists) {
                DB::table($pivot)->insert([
                    'gallery_id' => $row->model_id,
                    'media_id' => $row->id,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('media')
            ->where('model_type', 'voodbuilder_media_gallery')
            ->update([
                'model_type' => 'voodbuilder_media_vault',
                'model_id' => $vaultId,
            ]);
    }
};
