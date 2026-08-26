<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource;
use Voodflow\Vmedia\Models\MediaGallery;

class CreateMediaGallery extends CreateRecord
{
    protected static string $resource = MediaGalleryResource::class;

    /** @var list<string> */
    protected array $pendingGalleryMedia = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $raw = $data['gallery_media'] ?? [];
        $this->pendingGalleryMedia = is_array($raw)
            ? array_values(array_filter(array_map('strval', $raw)))
            : [];

        unset($data['gallery_media']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var MediaGallery $record */
        $record = $this->record;
        $record->syncOrderedMedia($this->pendingGalleryMedia);
    }
}
