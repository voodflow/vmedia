<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaTagResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Voodflow\Vmedia\Filament\Resources\MediaTagResource;

class ManageMediaTags extends ManageRecords
{
    protected static string $resource = MediaTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
