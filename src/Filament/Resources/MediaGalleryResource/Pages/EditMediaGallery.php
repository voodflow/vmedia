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

    /** @var list<int> */
    protected array $pendingTagIds = [];

    /** @var list<int> */
    protected array $pendingAllowedTagIds = [];

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
        $record->load(['tags', 'allowedTags']);

        $data['gallery_media'] = $record->orderedMediaUuids();
        $data['tag_ids'] = $record->tags->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $data['allowed_tag_ids'] = $record->allowedTags->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->captureVirtualFields($data);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var MediaGallery $record */
        $record = $this->record;
        $this->syncVirtualFields($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function captureVirtualFields(array &$data): void
    {
        $raw = $data['gallery_media'] ?? [];
        $this->pendingGalleryMedia = is_array($raw)
            ? array_values(array_filter(array_map('strval', $raw)))
            : [];

        $this->pendingTagIds = array_values(array_map('intval', (array) ($data['tag_ids'] ?? [])));
        $this->pendingAllowedTagIds = array_values(array_map('intval', (array) ($data['allowed_tag_ids'] ?? [])));

        unset($data['gallery_media'], $data['tag_ids'], $data['allowed_tag_ids']);
    }

    protected function syncVirtualFields(MediaGallery $record): void
    {
        if ($record->isAlbum()) {
            $record->syncOrderedMedia($this->pendingGalleryMedia);
            $record->tags()->sync($this->pendingTagIds);
            $record->allowedTags()->detach();
        }

        if ($record->isGroup()) {
            $record->allowedTags()->sync($this->pendingAllowedTagIds);
            $record->tags()->detach();
            $record->mediaItems()->detach();
        }
    }
}
