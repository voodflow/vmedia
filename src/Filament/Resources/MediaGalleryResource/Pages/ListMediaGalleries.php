<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;
use Voodflow\Vmedia\Models\MediaGallery;

class ListMediaGalleries extends ListRecords
{
    protected static string $resource = MediaGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('createFolder')
                ->label(__('vmedia::admin.galleries.actions.new_folder'))
                ->url(MediaGalleryResource::getUrl('create', ['kind' => MediaGallery::KIND_GROUP])),
            CreateAction::make()
                ->label(__('vmedia::admin.galleries.actions.new_album')),
        ];
    }
}
