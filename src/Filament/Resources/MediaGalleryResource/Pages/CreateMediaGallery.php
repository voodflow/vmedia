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

    /** @var list<int> */
    protected array $pendingTagIds = [];

    /** @var list<int> */
    protected array $pendingAllowedTagIds = [];

    public function mount(): void
    {
        parent::mount();

        if (request()->query('kind') === MediaGallery::KIND_GROUP) {
            $this->form->fill([
                'kind' => MediaGallery::KIND_GROUP,
                'sort_order' => 0,
                'is_public' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->captureVirtualFields($data);

        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null || $data['sort_order'] === '') {
            $data['sort_order'] = (int) (MediaGallery::query()->max('sort_order') ?? 0) + 1;
        }

        return $data;
    }

    protected function afterCreate(): void
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
        }

        if ($record->isGroup()) {
            $record->allowedTags()->sync($this->pendingAllowedTagIds);
        }
    }
}
