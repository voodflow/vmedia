<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\MediaLibrary;

class PublicGalleryController extends Controller
{
    public function __invoke(string $slug): View
    {
        $gallery = MediaGallery::query()
            ->where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $assets = MediaLibrary::listAssets(galleryId: (int) $gallery->getKey());

        return view('vmedia::public.gallery', [
            'gallery' => $gallery,
            'assets' => $assets,
        ]);
    }
}
