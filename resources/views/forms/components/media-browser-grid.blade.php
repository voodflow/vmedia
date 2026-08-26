@php
    /** @var list<array{uuid: string, name: string, thumb: string|null, src: string, type: string}> $assets */
    $assets = $assets ?? [];
    $multiple = (bool) ($multiple ?? false);
    $statePath = $getStatePath();
    $pageStatePath = (string) preg_replace('/\.media_uuids$/', '.browser_page', $statePath);
    $meta = $meta ?? [
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 20,
        'total' => 0,
        'has_more' => false,
    ];
    $currentPage = max(1, (int) ($meta['current_page'] ?? 1));
    $lastPage = max(1, (int) ($meta['last_page'] ?? 1));
    $total = (int) ($meta['total'] ?? 0);
    $perPage = (int) ($meta['per_page'] ?? 20);
@endphp

<div
    wire:key="vmedia-browser-p{{ $currentPage }}-{{ md5(json_encode(array_column($assets, 'uuid'))) }}"
    x-data="{
        selected: $wire.{{ '$entangle' }}('{{ $statePath }}'),
        multiple: {{ $multiple ? 'true' : 'false' }},
        view: localStorage.getItem('vmedia.browser.view') || 'grid',
        preview: null,
        setView(mode) {
            this.view = mode;
            localStorage.setItem('vmedia.browser.view', mode);
        },
        isSelected(uuid) {
            if (this.multiple) {
                return Array.isArray(this.selected) && this.selected.includes(uuid);
            }

            return this.selected === uuid;
        },
        toggle(uuid) {
            if (! this.multiple) {
                this.selected = this.selected === uuid ? null : uuid;

                return;
            }

            let list = Array.isArray(this.selected) ? [...this.selected] : [];
            const index = list.indexOf(uuid);

            if (index >= 0) {
                list.splice(index, 1);
            } else {
                list.push(uuid);
            }

            this.selected = list;
        },
        openPreview(event, asset) {
            event.stopPropagation();
            this.preview = asset;
            document.documentElement.classList.add('overflow-y-hidden');
        },
        closePreview() {
            this.preview = null;
            document.documentElement.classList.remove('overflow-y-hidden');
        },
        goToPage(page) {
            $wire.set(@js($pageStatePath), page);
        },
    }"
    class="vmedia-browser"
    @keydown.escape.window="if (preview) closePreview()"
>
    <div class="vmedia-browser__toolbar">
        <div class="vmedia-browser__view-toggle" role="group" aria-label="{{ __('vmedia::admin.picker.view_grid') }} / {{ __('vmedia::admin.picker.view_list') }}">
            <button
                type="button"
                class="vmedia-browser__view-btn"
                :class="view === 'grid' && 'is-active'"
                @click="setView('grid')"
            >
                <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                {{ __('vmedia::admin.picker.view_grid') }}
            </button>
            <button
                type="button"
                class="vmedia-browser__view-btn"
                :class="view === 'list' && 'is-active'"
                @click="setView('list')"
            >
                <x-filament::icon icon="heroicon-m-bars-3" class="h-4 w-4" />
                {{ __('vmedia::admin.picker.view_list') }}
            </button>
        </div>
    </div>

    <div class="vmedia-browser__scroll">
        @if ($assets === [])
            <p class="vmedia-browser__empty">
                {{ __('vmedia::admin.picker.browser_empty') }}
            </p>
        @else
            <ul x-show="view === 'grid'" class="vmedia-browser__grid">
                @foreach ($assets as $asset)
                    @php
                        $previewSrc = $asset['thumb'] ?: ($asset['type'] === 'image' ? ($asset['src'] ?? null) : null);
                    @endphp
                    <li>
                        <div
                            role="button"
                            tabindex="0"
                            class="vmedia-browser__tile"
                            :class="isSelected(@js($asset['uuid'])) && 'is-selected'"
                            @click="toggle(@js($asset['uuid']))"
                            @keydown.enter.prevent="toggle(@js($asset['uuid']))"
                            @keydown.space.prevent="toggle(@js($asset['uuid']))"
                            title="{{ $asset['name'] }}"
                        >
                            @if (filled($previewSrc))
                                <img src="{{ $previewSrc }}" alt="" loading="lazy" draggable="false" />
                            @else
                                @include('vmedia::forms.components.partials.file-type-icon', [
                                    'icon' => $asset['icon'] ?? 'file',
                                    'label' => $asset['icon_label'] ?? strtoupper((string) ($asset['type'] ?? 'file')),
                                ])
                            @endif

                            <div class="vmedia-browser__tile-veil" aria-hidden="true"></div>

                            <span class="vmedia-browser__check" aria-hidden="true">
                                <x-filament::icon icon="heroicon-m-check" class="h-3 w-3" />
                            </span>

                            @if (filled($asset['src']))
                                <button
                                    type="button"
                                    class="vmedia-browser__preview-btn"
                                    @click="openPreview($event, @js($asset))"
                                    title="{{ __('vmedia::admin.picker.preview') }}"
                                >
                                    <x-filament::icon icon="heroicon-m-magnifying-glass-plus" class="h-3.5 w-3.5" />
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <ul x-show="view === 'list'" x-cloak class="vmedia-browser__list">
                @foreach ($assets as $asset)
                    @php
                        $previewSrc = $asset['thumb'] ?: ($asset['type'] === 'image' ? ($asset['src'] ?? null) : null);
                    @endphp
                    <li
                        class="vmedia-browser__list-row"
                        :class="isSelected(@js($asset['uuid'])) && 'is-selected'"
                    >
                        <button
                            type="button"
                            class="vmedia-browser__list-thumb"
                            @click="openPreview($event, @js($asset))"
                            title="{{ __('vmedia::admin.picker.preview') }}"
                        >
                            @if (filled($previewSrc))
                                <img src="{{ $previewSrc }}" alt="" loading="lazy" draggable="false" />
                            @else
                                @include('vmedia::forms.components.partials.file-type-icon', [
                                    'icon' => $asset['icon'] ?? 'file',
                                    'label' => $asset['icon_label'] ?? strtoupper((string) ($asset['type'] ?? 'file')),
                                ])
                            @endif
                        </button>

                        <button
                            type="button"
                            class="vmedia-browser__list-meta"
                            @click="toggle(@js($asset['uuid']))"
                        >
                            <span class="vmedia-browser__list-name">{{ $asset['name'] }}</span>
                            <span class="vmedia-browser__list-type">{{ $asset['icon_label'] ?? $asset['type'] }}</span>
                        </button>

                        <span class="vmedia-browser__check" aria-hidden="true">
                            <x-filament::icon icon="heroicon-m-check" class="h-3 w-3" />
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($lastPage > 1 || $total > 0)
        <div class="vmedia-browser__pager">
            <p class="vmedia-browser__pager-summary">
                {{ __('vmedia::admin.picker.pagination_summary', [
                    'from' => $total === 0 ? 0 : (($currentPage - 1) * $perPage) + 1,
                    'to' => min($total, $currentPage * $perPage),
                    'total' => $total,
                ]) }}
            </p>

            <div class="vmedia-browser__pager-actions">
                <button
                    type="button"
                    class="vmedia-browser__pager-btn"
                    @click="goToPage({{ max(1, $currentPage - 1) }})"
                    @disabled($currentPage <= 1)
                >
                    {{ __('vmedia::admin.picker.prev') }}
                </button>

                <span class="vmedia-browser__pager-page">{{ $currentPage }} / {{ $lastPage }}</span>

                <button
                    type="button"
                    class="vmedia-browser__pager-btn"
                    @click="goToPage({{ min($lastPage, $currentPage + 1) }})"
                    @disabled($currentPage >= $lastPage)
                >
                    {{ __('vmedia::admin.picker.next') }}
                </button>
            </div>
        </div>
    @endif

    @include('vmedia::forms.components.media-preview-lightbox')
</div>
