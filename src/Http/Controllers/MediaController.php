<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\File;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\UploadGuard;

/**
 * Package-owned media browser endpoints (galleries + paginated list + upload + delete).
 */
class MediaController extends Controller
{
    use AuthorizesRequests;
    public function galleries(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MediaGallery::class);

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
        $this->authorize('viewAny', MediaItem::class);

        $type = $request->query('type');
        $type = is_string($type) && in_array($type, ['image', 'video'], true) ? $type : null;
        $galleryId = $request->query('gallery_id');
        $galleryId = is_numeric($galleryId) ? (int) $galleryId : null;
        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', config('vmedia.browser.per_page', 48));

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
        $this->authorize('create', MediaItem::class);

        $imageMaxKb = (int) config('vmedia.upload.image_max_kb', 8192);
        $videoMaxKb = (int) config('vmedia.upload.video_max_kb', 51200);
        $extensions = (array) config('vmedia.upload.allowed_extensions', []);
        $uploaded = $request->file('file');

        UploadGuard::assertSafeUpload($uploaded);

        $mime = (string) ($uploaded?->getMimeType() ?? '');
        $isVideo = str_starts_with($mime, 'video/');

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                File::types($extensions)->max($isVideo ? $videoMaxKb : $imageMaxKb),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        UploadGuard::assertAllowedMime($validated['file']);

        $media = MediaLibrary::store(
            $validated['file'],
            MediaGallery::default(),
            isset($validated['name']) ? (string) $validated['name'] : null,
            isset($validated['caption']) ? (string) $validated['caption'] : null,
        );
        $payload = MediaLibrary::toAssetPayload($media);

        return response()->json([
            'data' => [$payload['src']],
            'media' => $payload,
        ], 201);
    }

    public function destroy(MediaItem $media): JsonResponse
    {
        $this->authorize('delete', $media);

        if (! MediaLibrary::isVaultMedia($media)) {
            abort(404);
        }

        $media->delete();

        return response()->json(['deleted' => true]);
    }
}
