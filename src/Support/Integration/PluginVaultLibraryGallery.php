<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\Integration;

use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryUploadTarget;

/**
 * Default upload/browse album inside each plugin vault root folder.
 */
final class PluginVaultLibraryGallery
{
    public static function album(string $integrationSource): MediaGallery
    {
        $group = PluginVaultRootGroup::for($integrationSource);

        return GalleryUploadTarget::resolve((int) $group->getKey());
    }
}
