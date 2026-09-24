<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Support\Facades\Schema;
use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Guards gallery provisioning until hierarchy columns exist on vmedia_galleries.
 */
final class GalleryIntegrationSchema
{
    public static function isReady(): bool
    {
        if (! class_exists(MediaGallery::class)) {
            return false;
        }

        $table = (string) config('vmedia.tables.galleries', 'vmedia_galleries');

        try {
            return Schema::hasTable($table)
                && Schema::hasColumn($table, 'integration_source')
                && Schema::hasColumn($table, 'kind')
                && Schema::hasColumn($table, 'parent_id');
        } catch (\Throwable) {
            return false;
        }
    }
}
