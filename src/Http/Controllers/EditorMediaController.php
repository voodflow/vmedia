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
 * VoodBuilder editor Asset Manager endpoints (galleries + list + upload → default gallery).
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

        return response()->json([
            'data' => array_map(
                static fn (array $asset): array => [
                    'src' => $asset['src'],
                    'type' => $asset['type'],
                    'name' => $asset['name'],
                    'uuid' => $asset['uuid'],
                    'id' => $asset['id'],
                    'gallery_ids' => $asset['gallery_ids'],
                ],
                MediaLibrary::listAssets($type, $galleryId),
            ),
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

        // Frontend / editor uploads always land in the default gallery (membership).
        $media = MediaLibrary::store($validated['file'], MediaGallery::default());
        $payload = MediaLibrary::toAssetPayload($media);

        return response()->json([
            'data' => [$payload['src']],
            'media' => $payload,
        ]);
    }
}
