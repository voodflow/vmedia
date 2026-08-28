<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tagsTable = (string) config('vmedia.tables.tags', 'vmedia_tags');
        $galleryTagsTable = (string) config('vmedia.tables.gallery_tags', 'vmedia_gallery_tags');
        $mediaTagsTable = (string) config('vmedia.tables.media_tags', 'vmedia_media_tags');
        $allowedTagsTable = (string) config('vmedia.tables.gallery_allowed_tags', 'vmedia_gallery_allowed_tags');
        $galleriesTable = (string) config('vmedia.tables.galleries', 'voodbuilder_media_galleries');

        if (! Schema::hasTable($tagsTable)) {
            Schema::create($tagsTable, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('slug')->unique();
                $blueprint->string('type', 64)->nullable()->index();
                $blueprint->unsignedInteger('sort_order')->default(0);
                $blueprint->timestamps();
            });
        }

        if (! Schema::hasTable($galleryTagsTable)) {
            Schema::create($galleryTagsTable, function (Blueprint $blueprint) use ($galleriesTable, $tagsTable): void {
                $blueprint->id();
                $blueprint->foreignId('gallery_id')->constrained($galleriesTable)->cascadeOnDelete();
                $blueprint->foreignId('tag_id')->constrained($tagsTable)->cascadeOnDelete();
                $blueprint->timestamps();
                $blueprint->unique(['gallery_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable($mediaTagsTable)) {
            Schema::create($mediaTagsTable, function (Blueprint $blueprint) use ($tagsTable): void {
                $blueprint->id();
                $blueprint->unsignedBigInteger('media_id');
                $blueprint->foreignId('tag_id')->constrained($tagsTable)->cascadeOnDelete();
                $blueprint->timestamps();
                $blueprint->unique(['media_id', 'tag_id']);
                $blueprint->index('media_id');
            });
        }

        if (! Schema::hasTable($allowedTagsTable)) {
            Schema::create($allowedTagsTable, function (Blueprint $blueprint) use ($galleriesTable, $tagsTable): void {
                $blueprint->id();
                $blueprint->foreignId('gallery_id')->constrained($galleriesTable)->cascadeOnDelete();
                $blueprint->foreignId('tag_id')->constrained($tagsTable)->cascadeOnDelete();
                $blueprint->timestamps();
                $blueprint->unique(['gallery_id', 'tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('vmedia.tables.gallery_allowed_tags', 'vmedia_gallery_allowed_tags'));
        Schema::dropIfExists((string) config('vmedia.tables.media_tags', 'vmedia_media_tags'));
        Schema::dropIfExists((string) config('vmedia.tables.gallery_tags', 'vmedia_gallery_tags'));
        Schema::dropIfExists((string) config('vmedia.tables.tags', 'vmedia_tags'));
    }
};
