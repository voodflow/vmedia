<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Voodflow\Vmedia\Support\MediaExplorer as MediaExplorerQuery;

/**
 * Livewire component behind the VoodBuilder "Media explorer" block.
 *
 * Server-side search, type and album filters, incremental "load more" and a real
 * result count — the things a static Tailwind block cannot do. Reads are public:
 * the component is mounted on public pages and the Livewire endpoint is open, so
 * there is no mutation here and {@see MediaExplorerQuery} only ever exposes media
 * that sits in a public album.
 *
 * The public properties are part of the component snapshot; only the filters the
 * block actually exposes are honoured in `render()`, so hiding a control also stops
 * it from driving the query.
 */
final class MediaExplorer extends Component
{
    /**
     * Registered Livewire name. It lives here, on the always-loadable component, so
     * the block class (which needs VoodBuilder) is never the only place it exists.
     */
    public const NAME = 'vmedia-media-explorer';

    public string $heading = '';

    public int $perPage = MediaExplorerQuery::DEFAULT_PER_PAGE;

    public bool $showSearch = true;

    public bool $showTypeFilter = true;

    public bool $showAlbumFilter = true;

    public string $search = '';

    public string $type = '';

    public string $albumId = '';

    public int $limit = 0;

    /**
     * Lightbox state: the id of the media the visitor opened, or null when closed.
     *
     * Part of the snapshot on purpose — selection, navigation and the image/caption
     * all come from the same filtered window the grid renders, and the modal survives
     * a Livewire morph because it lives inside this component's root.
     */
    public ?int $activeMediaId = null;

    public function mount(
        string $heading = '',
        int $perPage = MediaExplorerQuery::DEFAULT_PER_PAGE,
        bool $showSearch = true,
        bool $showTypeFilter = true,
        bool $showAlbumFilter = true,
    ): void {
        $this->heading = trim($heading);
        $this->perPage = MediaExplorerQuery::normalizePerPage($perPage);
        $this->showSearch = $showSearch;
        $this->showTypeFilter = $showTypeFilter;
        $this->showAlbumFilter = $showAlbumFilter;
        $this->limit = $this->perPage;
    }

    public function updatedSearch(): void
    {
        // A new query invalidates the items already loaded.
        $this->resetWindow();
        $this->closeMedia();
    }

    public function updatedType(): void
    {
        $this->resetWindow();
        $this->closeMedia();
    }

    public function updatedAlbumId(): void
    {
        $this->resetWindow();
        $this->closeMedia();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetWindow();
        $this->closeMedia();
    }

    public function loadMore(): void
    {
        $this->limit = MediaExplorerQuery::normalizeWindow(
            $this->limit + $this->perPage,
            $this->perPage,
        );
    }

    /**
     * Open the lightbox on one media of the *currently visible* window.
     *
     * Ids outside the active search / type / album scope are ignored, so the public
     * endpoint cannot be used to reveal a media the filters (or the public-album
     * boundary) excluded.
     */
    public function openMedia(int $mediaId): void
    {
        if (! in_array($mediaId, $this->visibleMediaIds(), true)) {
            return;
        }

        $this->activeMediaId = $mediaId;
    }

    public function closeMedia(): void
    {
        $this->activeMediaId = null;
    }

    public function nextMedia(): void
    {
        $this->stepMedia(1);
    }

    public function previousMedia(): void
    {
        $this->stepMedia(-1);
    }

    public function render(): View
    {
        [$type, $albumId, $search, $window] = $this->queryScope();
        $perPage = MediaExplorerQuery::normalizePerPage($this->perPage);

        $result = MediaExplorerQuery::paginate($type, $albumId, $search, 1, $window);

        $assets = $result['data'];
        $activeIndex = $this->activeIndexIn($assets);

        return view('vmedia::blocks.media-explorer', [
            'heading' => $this->heading,
            'live' => true,
            'authoring' => false,
            'showSearch' => $this->showSearch,
            'showTypeFilter' => $this->showTypeFilter,
            'showAlbumFilter' => $this->showAlbumFilter,
            'search' => $search,
            'type' => $type ?? '',
            'albumId' => $albumId,
            'perPage' => $perPage,
            'assets' => $assets,
            'activeAsset' => $activeIndex === null ? null : $assets[$activeIndex],
            'hasPrevious' => $activeIndex !== null && $activeIndex > 0,
            'hasNext' => $activeIndex !== null && $activeIndex < count($assets) - 1,
            'total' => $result['meta']['total'],
            'hasMore' => $result['meta']['has_more'],
            'typeCounts' => $this->showTypeFilter ? MediaExplorerQuery::typeCounts($albumId, $search) : [],
            'albums' => $this->showAlbumFilter ? MediaExplorerQuery::albumOptions() : [],
        ]);
    }

    private function resetWindow(): void
    {
        $this->limit = MediaExplorerQuery::normalizePerPage($this->perPage);
    }

    /**
     * Filters that actually drive the query, plus the loaded window size. The controls
     * the block hides are ignored here exactly like they are in `render()`.
     *
     * @return array{0: ?string, 1: ?int, 2: string, 3: int}
     */
    private function queryScope(): array
    {
        $perPage = MediaExplorerQuery::normalizePerPage($this->perPage);

        return [
            $this->showTypeFilter ? MediaExplorerQuery::normalizeType($this->type) : null,
            $this->showAlbumFilter ? MediaExplorerQuery::normalizeAlbumId($this->albumId) : null,
            $this->showSearch ? trim($this->search) : '',
            MediaExplorerQuery::normalizeWindow($this->limit, $perPage),
        ];
    }

    /**
     * @return list<int>
     */
    private function visibleMediaIds(): array
    {
        [$type, $albumId, $search, $window] = $this->queryScope();

        return array_values(array_map(
            static fn (array $asset): int => (int) ($asset['id'] ?? 0),
            MediaExplorerQuery::paginate($type, $albumId, $search, 1, $window)['data'],
        ));
    }

    /**
     * Move one step inside the visible window, clamped at both ends: the arrows never
     * wrap into media the current filters hide.
     */
    private function stepMedia(int $delta): void
    {
        $ids = $this->visibleMediaIds();
        $index = $this->activeMediaId === null
            ? false
            : array_search($this->activeMediaId, $ids, true);

        if ($index === false) {
            $this->activeMediaId = null;

            return;
        }

        $target = $ids[$index + $delta] ?? null;

        if ($target === null) {
            return;
        }

        $this->activeMediaId = $target;
    }

    /**
     * @param  list<array<string, mixed>>  $assets
     */
    private function activeIndexIn(array $assets): ?int
    {
        if ($this->activeMediaId === null) {
            return null;
        }

        $index = array_search($this->activeMediaId, array_column($assets, 'id'), true);

        return $index === false ? null : (int) $index;
    }
}
