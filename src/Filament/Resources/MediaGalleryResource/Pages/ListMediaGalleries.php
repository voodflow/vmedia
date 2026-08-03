<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;

class ListMediaGalleries extends ListRecords
{
    protected static string $resource = MediaGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
