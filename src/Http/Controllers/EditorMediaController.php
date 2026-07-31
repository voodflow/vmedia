<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\File;
use Voodflow\VoodbuilderMedia\Support\MediaLibrary;

/**
 * VoodBuilder editor Asset Manager endpoints (list + upload → Spatie galleries).
 */
class EditorMediaController extends Controller
{
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
                    'gallery_id' => $asset['gallery_id'],
                ],
                MediaLibrary::listAssets($type, $galleryId),
            ),
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

        $media = MediaLibrary::store($validated['file']);
        $payload = MediaLibrary::toAssetPayload($media);

        return response()->json([
            'data' => [$payload['src']],
            'media' => $payload,
        ]);
    }
}
