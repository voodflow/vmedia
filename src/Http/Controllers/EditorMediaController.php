<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\File;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;
use Voodflow\VoodbuilderMedia\Support\MediaLibrary;

/**
 * VoodBuilder editor media browser endpoints (galleries + paginated list + upload → default).
 */
class EditorMediaController extends Controller
{
    public function galleries(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $type = is_string($type) && in_array($type, ['image', 'video'], true) ? $type : null;
        $default = MediaGallery::default();

        return response()->json([
            'data' => MediaLibrary::listGalleries($type),
            'default_gallery_id' => (int) $default->getKey(),
            'upload_gallery_id' => (int) $default->getKey(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $type = is_string($type) && in_array($type, ['image', 'video'], true) ? $type : null;
        $galleryId = $request->query('gallery_id');
        $galleryId = is_numeric($galleryId) ? (int) $galleryId : null;
        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', config('voodbuilder-media.browser.per_page', 48));

        $result = MediaLibrary::paginateAssets($type, $galleryId, $search !== '' ? $search : null, $page, $perPage);

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
            'default_gallery_id' => (int) MediaGallery::default()->getKey(),
            'upload_gallery_id' => (int) MediaGallery::default()->getKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $imageMaxKb = (int) config('voodbuilder-media.upload.image_max_kb', 8192);
        $videoMaxKb = (int) config('voodbuilder-media.upload.video_max_kb', 51200);
        $uploaded = $request->file('file');
        $mime = (string) ($uploaded?->getMimeType() ?? '');
        $isVideo = str_starts_with($mime, 'video/');

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                File::types([
                    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
                    'mp4', 'webm', 'ogg', 'mov', 'm4v',
                ])->max($isVideo ? $videoMaxKb : $imageMaxKb),
            ],
        ]);

        $media = MediaLibrary::store($validated['file'], MediaGallery::default());
        $payload = MediaLibrary::toAssetPayload($media);

        return response()->json([
            'data' => [$payload['src']],
            'media' => $payload,
        ]);
    }
}
