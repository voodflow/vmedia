<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Database\Eloquent\Builder;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;

/**
 * Public-facing media query behind the VoodBuilder "Media explorer" Livewire block.
 *
 * The picker is an author surface; the explorer is browsed by anonymous visitors
 * through a public Livewire endpoint. Every read here is therefore scoped to media
 * that belongs to at least one **public** album, so a private album can never leak
 * through search, a type tab or the album filter. Gallery membership returned to the
 * component is trimmed to public albums for the same reason.
 */
final class MediaExplorer
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_FILE = 'file';

    /** @var list<string> */
    public const TYPES = [self::TYPE_IMAGE, self::TYPE_VIDEO, self::TYPE_FILE];

    public const DEFAULT_PER_PAGE = 12;

    public const MAX_PER_PAGE = 48;

    /**
     * Hard ceiling for the incremental "load more" window.
     *
     * Load-more grows the window by one page per click and the endpoint is public,
     * so without a cap a client could ask the server for an unbounded result set.
     */
    public const MAX_WINDOW = 480;

    public static function normalizeType(mixed $type): ?string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, self::TYPES, true) ? $type : null;
    }

    public static function normalizePerPage(mixed $perPage): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $perPage));
    }

    public static function normalizeWindow(mixed $window, int $perPage): int
    {
        $perPage = self::normalizePerPage($perPage);

        return max($perPage, min(self::MAX_WINDOW, (int) $window));
    }

    public static function normalizeAlbumId(mixed $albumId): ?int
    {
        $albumId = is_numeric($albumId) ? (int) $albumId : 0;

        return $albumId > 0 ? $albumId : null;
    }

    /**
     * @return array{
     *   data: list<array<string, mixed>>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     * }
     */
    public static function paginate(
        ?string $type = null,
        ?int $albumId = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): array {
        $perPage = self::normalizePerPage($perPage);
        $page = max(1, $page);

        $paginator = self::baseQuery($type, $albumId, $search)
            ->orderByDesc((new MediaItem)->qualifyColumn('id'))
            ->paginate(perPage: $perPage, page: $page);

        return [
            'data' => $paginator->getCollection()
                ->map(static fn (MediaItem $media): array => MediaLibrary::toAssetPayload($media))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * Media counts per type for the current scope, so the type tabs can show honest
     * numbers (the type filter itself is deliberately not applied here).
     *
     * @return array{image: int, video: int, file: int, total: int}
     */
    public static function typeCounts(?int $albumId = null, ?string $search = null): array
    {
        $rows = self::baseQuery(null, $albumId, $search)
            ->selectRaw('collection_name, count(*) as aggregate')
            ->groupBy('collection_name')
            ->pluck('aggregate', 'collection_name');

        $counts = [
            self::TYPE_IMAGE => (int) ($rows[MediaGallery::COLLECTION_IMAGES] ?? 0),
            self::TYPE_VIDEO => (int) ($rows[MediaGallery::COLLECTION_VIDEOS] ?? 0),
            self::TYPE_FILE => (int) ($rows[MediaGallery::COLLECTION_FILES] ?? 0),
        ];

        return [
            ...$counts,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Raw attribution string for one asset payload, or '' when the media has none.
     *
     * The media row carries no dedicated credit / attribution / source column: the
     * only text stores are `custom_properties.credits` (rich HTML, the same source the
     * gallery credits strip uses) and `custom_properties.caption` (the descriptive
     * field the payload exposes as `caption`, where attribution is normally written).
     * Credits win when both are set; nothing is invented when both are empty.
     *
     * @param  array<string, mixed>  $asset
     */
    public static function creditSource(array $asset): string
    {
        $credits = trim((string) ($asset['credits'] ?? ''));

        if ($credits !== '') {
            return $credits;
        }

        return trim((string) ($asset['caption'] ?? ''));
    }

    /**
     * Sanitized credit line (plain text + http(s) links only), '' when absent.
     *
     * @param  array<string, mixed>  $asset
     */
    public static function creditHtml(array $asset): string
    {
        return CreditsHtml::sanitize(self::creditSource($asset));
    }

    /**
     * Public albums that hold at least one media item — the visitor's album filter.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public static function albumOptions(): array
    {
        return MediaGallery::query()
            ->with('parent:id,name,parent_id')
            ->where('kind', MediaGallery::KIND_ALBUM)
            ->where('is_public', true)
            ->withCount('mediaItems')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(static fn (MediaGallery $album): bool => (int) ($album->media_items_count ?? 0) > 0)
            ->map(static fn (MediaGallery $album): array => [
                'id' => (int) $album->getKey(),
                'name' => GalleryDisplay::navLabel($album),
                'count' => (int) ($album->media_items_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<MediaItem>
     */
    protected static function baseQuery(?string $type, ?int $albumId, ?string $search): Builder
    {
        $mediaTable = (new MediaItem)->getTable();

        $query = MediaItem::query()
            // Only public galleries are eager-loaded: the payload exposes memberships.
            ->with(['galleries' => static function ($builder): void {
                $builder->where('is_public', true);
            }])
            ->where("{$mediaTable}.model_type", (new MediaVault)->getMorphClass())
            ->whereIn("{$mediaTable}.collection_name", [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
                MediaGallery::COLLECTION_FILES,
            ]);

        $type = self::normalizeType($type);

        if ($type === self::TYPE_IMAGE) {
            $query->where("{$mediaTable}.collection_name", MediaGallery::COLLECTION_IMAGES);
        } elseif ($type === self::TYPE_VIDEO) {
            $query->where("{$mediaTable}.collection_name", MediaGallery::COLLECTION_VIDEOS);
        } elseif ($type === self::TYPE_FILE) {
            $query->where("{$mediaTable}.collection_name", MediaGallery::COLLECTION_FILES);
        }

        // Public-only boundary: anonymous visitors never reach a private album.
        $query->whereHas('galleries', static function (Builder $builder) use ($albumId): void {
            $builder->where('is_public', true);

            if ($albumId !== null) {
                $builder->whereKey($albumId);
            }
        });

        $search = is_string($search) ? trim($search) : '';

        if ($search !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(static function (Builder $builder) use ($term, $mediaTable): void {
                $builder
                    ->where("{$mediaTable}.name", 'like', $term)
                    ->orWhere("{$mediaTable}.file_name", 'like', $term)
                    ->orWhere("{$mediaTable}.custom_properties->caption", 'like', $term)
                    ->orWhere("{$mediaTable}.custom_properties->alt", 'like', $term);
            });
        }

        return $query;
    }
}
