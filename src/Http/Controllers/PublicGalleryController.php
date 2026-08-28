<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\GalleryAggregate;
use Voodflow\Vmedia\Support\GalleryPath;
use Voodflow\Vmedia\Support\MediaLibrary;

class PublicGalleryController extends Controller
{
    public function __invoke(Request $request, string $path): View
    {
        $gallery = GalleryPath::resolve($path);

        if ($gallery === null || ! $gallery->is_public) {
            abort(404);
        }

        if ($gallery->isGroup()) {
            return $this->groupView($request, $gallery);
        }

        return $this->albumView($gallery);
    }

    protected function groupView(Request $request, MediaGallery $group): View
    {
        $mode = (string) $request->query('mode', 'children');
        $order = (string) $request->query('order', 'manual');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(96, (int) $request->query('per_page', config('vmedia.browser.per_page', 48))));

        if ($mode === 'media') {
            $result = GalleryAggregate::paginateMedia($group, [
                'order' => in_array($order, ['manual', 'newest', 'oldest', 'random'], true) ? $order : 'manual',
                'page' => $page,
                'per_page' => $perPage,
                'tag_ids' => array_filter(array_map('intval', (array) $request->query('tags', []))),
            ]);

            return view('vmedia::public.group-media', [
                'gallery' => $group,
                'assets' => $result['data'],
                'meta' => $result['meta'],
                'path' => GalleryPath::toPath($group),
            ]);
        }

        return view('vmedia::public.group-children', [
            'gallery' => $group,
            'children' => GalleryAggregate::childAlbumPayloads($group),
            'path' => GalleryPath::toPath($group),
        ]);
    }

    protected function albumView(MediaGallery $gallery): View
    {
        $assets = MediaLibrary::listAssets(galleryId: (int) $gallery->getKey());

        return view('vmedia::public.gallery', [
            'gallery' => $gallery,
            'assets' => $assets,
            'path' => GalleryPath::toPath($gallery),
        ]);
    }
}
