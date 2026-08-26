<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources\MediaItemResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
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
            MediaItemResource::importZipAction(),
            MediaItemResource::refineUploadedAction(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return __('vmedia::admin.library.plural');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('vmedia::admin.library.intro');
    }
}
