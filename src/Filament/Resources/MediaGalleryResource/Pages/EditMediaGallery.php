<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;
use Voodflow\Vmedia\Models\MediaGallery;

class EditMediaGallery extends EditRecord
{
    protected static string $resource = MediaGalleryResource::class;

    /** @var list<string> */
    protected array $pendingGalleryMedia = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (): bool => (bool) $this->record?->is_default),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var MediaGallery $record */
        $record = $this->record;
        $data['gallery_media'] = $record->orderedMediaUuids();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $raw = $data['gallery_media'] ?? [];
        $this->pendingGalleryMedia = is_array($raw)
            ? array_values(array_filter(array_map('strval', $raw)))
            : [];

        unset($data['gallery_media']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var MediaGallery $record */
        $record = $this->record;
        $record->syncOrderedMedia($this->pendingGalleryMedia);
    }
}
