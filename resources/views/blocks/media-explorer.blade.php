@php
    /**
     * Shared markup for the VoodMedia "Media explorer" Livewire block.
     *
     * Both the Livewire component view and the editor preview render this partial, so
     * the compiled Tailwind set (built from the saved preview HTML) also covers the
     * mounted component (see EditorLivewireBlockCss).
     *
     * $live selects the *behaviour* of the controls, not their presence:
     *   - true  → real Livewire bindings (search, type tabs, album filter, load more);
     *   - false → the same structure and classes with inert controls, because the
     *     canvas never mounts Livewire and must not render a `wire:*` attribute.
     *
     * $authoring adds editor-only data-vb-field markers: for a dynamic block the
     * Content panel writes their values into `data-voodbuilder-config`
     * (see declarative-fields.js). The published page drops them, so the mount stays
     * free of editor attributes.
     *
     * No `voodbuilder-editor-*` plumbing here on purpose: a Livewire mount must not be
     * wrapped, so the block owns plain markup only.
     */
    $live = $live ?? false;
    $authoring = $authoring ?? false;
    $heading = (string) ($heading ?? '');
    $showSearch = (bool) ($showSearch ?? true);
    $showTypeFilter = (bool) ($showTypeFilter ?? true);
    $showAlbumFilter = (bool) ($showAlbumFilter ?? true);
    $search = (string) ($search ?? '');
    $type = (string) ($type ?? '');
    $albumId = $albumId ?? null;
    $perPage = (int) ($perPage ?? \Voodflow\Vmedia\Support\MediaExplorer::DEFAULT_PER_PAGE);
    $assets = is_array($assets ?? null) ? $assets : [];
    $total = (int) ($total ?? count($assets));
    $hasMore = (bool) ($hasMore ?? false);
    $typeCounts = is_array($typeCounts ?? null) ? $typeCounts : [];
    $albums = is_array($albums ?? null) ? $albums : [];
    $shown = count($assets);

    $tabs = [
        ['value' => '', 'label' => __('vmedia::admin.editor.explorer_all'), 'count' => (int) ($typeCounts['total'] ?? $total)],
        ['value' => 'image', 'label' => __('vmedia::admin.editor.explorer_photos'), 'count' => (int) ($typeCounts['image'] ?? 0)],
        ['value' => 'video', 'label' => __('vmedia::admin.editor.explorer_videos'), 'count' => (int) ($typeCounts['video'] ?? 0)],
        ['value' => 'file', 'label' => __('vmedia::admin.editor.explorer_documents'), 'count' => (int) ($typeCounts['file'] ?? 0)],
    ];

    $tabClass = static fn (string $value): string => $value === $type
        ? 'border-vp-brand-1 bg-vp-brand-1 text-white'
        : 'border-vp-divider bg-vp-bg text-vp-text-2 hover:text-vp-text-1';
@endphp

<section class="vmedia-media-explorer rounded-2xl border border-vp-divider bg-vp-bg-elv p-6">
    @if ($authoring)
        {{-- Editor-only markers for the declarative Content panel (dynamic-block config). --}}
        <span
            class="sr-only"
            data-vb-field="per_page"
            data-vb-field-type="number"
            data-vb-field-label="{{ __('vmedia::admin.editor.explorer_per_page') }}"
        >{{ $perPage }}</span>
        <span
            class="sr-only"
            data-vb-field="show_search"
            data-vb-field-type="status"
            data-vb-status="{{ $showSearch ? 'yes' : 'no' }}"
            data-vb-field-label="{{ __('vmedia::admin.editor.explorer_show_search') }}"
        >{{ $showSearch ? 'yes' : 'no' }}</span>
        <span
            class="sr-only"
            data-vb-field="show_type_filter"
            data-vb-field-type="status"
            data-vb-status="{{ $showTypeFilter ? 'yes' : 'no' }}"
            data-vb-field-label="{{ __('vmedia::admin.editor.explorer_show_type_filter') }}"
        >{{ $showTypeFilter ? 'yes' : 'no' }}</span>
        <span
            class="sr-only"
            data-vb-field="show_album_filter"
            data-vb-field-type="status"
            data-vb-status="{{ $showAlbumFilter ? 'yes' : 'no' }}"
            data-vb-field-label="{{ __('vmedia::admin.editor.explorer_show_album_filter') }}"
        >{{ $showAlbumFilter ? 'yes' : 'no' }}</span>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <h2
            class="text-xl font-semibold text-vp-text-1"
            @if ($authoring) data-vb-field="heading" data-vb-field-type="text" data-vb-field-label="{{ __('vmedia::admin.editor.explorer_heading') }}" @endif
        >{{ $heading }}</h2>

        @if ($showSearch)
            <label class="block w-full sm:w-64">
                <span class="sr-only">{{ __('vmedia::admin.editor.explorer_search') }}</span>
                <input
                    type="search"
                    @if ($live)
                        wire:model.live.debounce.300ms="search"
                    @else
                        disabled
                        aria-disabled="true"
                    @endif
                    placeholder="{{ __('vmedia::admin.editor.explorer_search_placeholder') }}"
                    class="w-full rounded-lg border border-vp-divider bg-vp-bg px-3 py-2 text-sm text-vp-text-1"
                />
            </label>
        @endif
    </div>

    @if ($showTypeFilter || ($showAlbumFilter && $albums !== []))
        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if ($showTypeFilter)
                <div class="flex flex-wrap gap-2" role="tablist">
                    @foreach ($tabs as $tab)
                        @if ($live)
                            <button
                                type="button"
                                wire:click="setType('{{ $tab['value'] }}')"
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition {{ $tabClass($tab['value']) }}"
                            >
                                {{ $tab['label'] }}
                                <span class="text-vp-text-3">{{ $tab['count'] }}</span>
                            </button>
                        @else
                            {{--
                                Inert stand-in: same tag and classes as the published tab.
                                `data-voodbuilder-skip-cta="true"` (the literal string) stops the
                                editor's CTA scanner from morphing it into a link CTA.
                            --}}
                            <button
                                type="button"
                                disabled
                                aria-disabled="true"
                                data-voodbuilder-skip-cta="true"
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition {{ $tabClass($tab['value']) }}"
                            >
                                {{ $tab['label'] }}
                                <span class="text-vp-text-3">{{ $tab['count'] }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($showAlbumFilter && $albums !== [])
                <label class="block w-full sm:w-64">
                    <span class="sr-only">{{ __('vmedia::admin.editor.explorer_album') }}</span>
                    <select
                        @if ($live)
                            wire:model.live="albumId"
                        @else
                            disabled
                            aria-disabled="true"
                        @endif
                        class="w-full rounded-lg border border-vp-divider bg-vp-bg px-3 py-2 text-sm text-vp-text-1"
                    >
                        <option value="">{{ __('vmedia::admin.editor.explorer_all_albums') }}</option>
                        @foreach ($albums as $album)
                            <option value="{{ $album['id'] }}" @if ((string) $albumId === (string) $album['id']) selected @endif>
                                {{ $album['name'] }} ({{ $album['count'] }})
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
    @endif

    <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($assets as $asset)
            <li
                class="vmedia-media-explorer__item min-w-0"
                @if ($live) wire:key="vmedia-media-{{ $asset['id'] ?? $loop->index }}" @endif
            >
                {{--
                    The tile is an <a>, not a <button>: opening the full asset in a new tab is
                    the only per-item action, and anchors are not morphed to CTAs here because
                    `looksLikeButton()` needs own padding, which this anchor does not carry;
                    `data-voodbuilder-skip-cta` keeps it out of the text-link pass anyway.
                --}}
                <a
                    href="{{ $asset['display'] ?? $asset['src'] ?? '#' }}"
                    target="_blank"
                    rel="noopener"
                    data-voodbuilder-skip-cta="true"
                    class="group block overflow-hidden rounded-xl border border-vp-divider bg-vp-bg"
                >
                    <img
                        src="{{ $asset['thumb'] ?? $asset['src'] ?? '' }}"
                        alt="{{ $asset['alt'] ?? $asset['name'] ?? '' }}"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:brightness-105"
                    />
                    <span class="block px-3 py-2">
                        <span class="block truncate text-sm font-medium text-vp-text-1">{{ $asset['name'] ?? '' }}</span>
                        <span class="mt-0.5 block text-xs text-vp-text-3">{{ $asset['icon_label'] ?? '' }}</span>
                    </span>
                </a>
            </li>
        @empty
            <li class="col-span-full rounded-xl border border-vp-divider bg-vp-bg p-6 text-center text-sm text-vp-text-2">
                {{ __('vmedia::admin.editor.explorer_empty') }}
            </li>
        @endforelse
    </ul>

    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-vp-text-3">
            {{ __('vmedia::admin.editor.explorer_count', ['shown' => $shown, 'total' => $total]) }}
        </p>

        @if ($hasMore)
            @if ($live)
                <button
                    type="button"
                    wire:click="loadMore"
                    class="rounded-lg bg-vp-brand-1 px-3 py-2 text-xs font-semibold text-white"
                >
                    {{ __('vmedia::admin.editor.explorer_load_more') }}
                </button>
            @else
                <button
                    type="button"
                    disabled
                    aria-disabled="true"
                    data-voodbuilder-skip-cta="true"
                    class="rounded-lg bg-vp-brand-1 px-3 py-2 text-xs font-semibold text-white"
                >
                    {{ __('vmedia::admin.editor.explorer_load_more') }}
                </button>
            @endif
        @endif
    </div>
</section>
