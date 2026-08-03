<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaItemResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use Voodflow\Vmedia\Filament\Resources\MediaItemResource;
use Voodflow\Vmedia\Models\MediaGallery;

class ManageMediaItems extends ManageRecords
{
    protected static string $resource = MediaItemResource::class;

    public function mount(): void
    {
        MediaGallery::default();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            MediaItemResource::uploadAction(),
            MediaItemResource::refineUploadedAction(),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('vmedia::admin.library.plural');
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return __('vmedia::admin.library.intro');
    }
}
