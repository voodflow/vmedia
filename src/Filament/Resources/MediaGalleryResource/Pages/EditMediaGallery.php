<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource;

class EditMediaGallery extends EditRecord
{
    protected static string $resource = MediaGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (): bool => (bool) $this->record?->is_default),
        ];
    }
}
