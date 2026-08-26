<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\MediaLibrary;

class PublicGallery extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(): View
    {
        $gallery = MediaGallery::query()
            ->where('slug', $this->slug)
            ->where('is_public', true)
            ->firstOrFail();

        $assets = MediaLibrary::listAssets(galleryId: (int) $gallery->getKey());

        return view('vmedia::livewire.public-gallery', [
            'gallery' => $gallery,
            'assets' => $assets,
        ]);
    }
}
