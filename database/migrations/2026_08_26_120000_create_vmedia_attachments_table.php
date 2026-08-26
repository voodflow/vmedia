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

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->morphs('attachable');
            $table->unsignedBigInteger('media_id');
            $table->string('collection')->default('default');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['attachable_type', 'attachable_id', 'collection', 'media_id'],
                'vmedia_attach_unique',
            );
            $table->index('media_id');
            $table->index(['attachable_type', 'attachable_id', 'collection'], 'vmedia_attach_collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('vmedia.tables.attachments', 'vmedia_attachments'));
    }
};
