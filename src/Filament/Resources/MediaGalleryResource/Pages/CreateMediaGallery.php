<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource;

class CreateMediaGallery extends CreateRecord
{
    protected static string $resource = MediaGalleryResource::class;
}
