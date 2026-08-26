<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaVault;

/**
 * Move Spatie rows owned by domain models into the vault and attach via vmedia_attachments.
 */
final class RehomeModelMedia
{
    /**
     * @param  list<class-string<Model>>  $models
     */
    public static function migrate(array $models): int
    {
        if (! Schema::hasTable('media')) {
            return 0;
        }

        $attachments = (string) config('vmedia.tables.attachments', 'vmedia_attachments');

        if (! Schema::hasTable($attachments)) {
            return 0;
        }

        $vault = MediaVault::current();
        $moved = 0;

        foreach ($models as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $morph = (new $class)->getMorphClass();
            $rows = DB::table('media')->where('model_type', $morph)->get();

            foreach ($rows as $row) {
                $logicalCollection = (string) $row->collection_name;
                $vaultCollection = self::vaultCollection((string) ($row->mime_type ?? ''), $logicalCollection);

                $exists = DB::table($attachments)
                    ->where('attachable_type', $morph)
                    ->where('attachable_id', $row->model_id)
                    ->where('collection', $logicalCollection)
                    ->where('media_id', $row->id)
                    ->exists();

                if (! $exists) {
                    $now = now();
                    DB::table($attachments)->insert([
                        'attachable_type' => $morph,
                        'attachable_id' => $row->model_id,
                        'media_id' => $row->id,
                        'collection' => $logicalCollection,
                        'sort_order' => (int) ($row->order_column ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('media')->where('id', $row->id)->update([
                    'model_type' => $vault->getMorphClass(),
                    'model_id' => $vault->getKey(),
                    'collection_name' => $vaultCollection,
                ]);

                $moved++;
            }
        }

        return $moved;
    }

    public static function vaultCollection(string $mime, string $logicalCollection): string
    {
        if (str_starts_with($mime, 'video/')) {
            return MediaGallery::COLLECTION_VIDEOS;
        }

        if (str_starts_with($mime, 'image/')) {
            return MediaGallery::COLLECTION_IMAGES;
        }

        if (in_array($logicalCollection, ['images', 'gallery', 'featured', 'logo'], true)) {
            return MediaGallery::COLLECTION_IMAGES;
        }

        return MediaGallery::COLLECTION_FILES;
    }
}
