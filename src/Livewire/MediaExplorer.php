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
    }

    public function updatedType(): void
    {
        $this->resetWindow();
    }

    public function updatedAlbumId(): void
    {
        $this->resetWindow();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetWindow();
    }

    public function loadMore(): void
    {
        $this->limit = MediaExplorerQuery::normalizeWindow(
            $this->limit + $this->perPage,
            $this->perPage,
        );
    }

    public function render(): View
    {
        $search = $this->showSearch ? trim($this->search) : '';
        $type = $this->showTypeFilter ? MediaExplorerQuery::normalizeType($this->type) : null;
        $albumId = $this->showAlbumFilter ? MediaExplorerQuery::normalizeAlbumId($this->albumId) : null;
        $perPage = MediaExplorerQuery::normalizePerPage($this->perPage);
        $window = MediaExplorerQuery::normalizeWindow($this->limit, $perPage);

        $result = MediaExplorerQuery::paginate($type, $albumId, $search, 1, $window);

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
            'assets' => $result['data'],
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
}
