@php
    /**
     * Lightbox for the VoodMedia "Media explorer" block.
     *
     * Rendered inside the Livewire component root: the mount root carries
     * `wire:ignore.self`, so the modal has to be part of the component's own markup to
     * survive the morph. The selection is Livewire state (`$activeMediaId`); the
     * directives here only translate keys / clicks into component calls.
     *
     * The canvas never mounts Livewire, so the same markup is emitted inert — no
     * `wire:*`, disabled controls — inside a `hidden` wrapper. The classes still reach
     * the compiled page stylesheet because EditorLivewireBlockCss compiles them from
     * this preview, but nothing can open on the canvas.
     *
     * $asset is one payload entry from the *filtered* window the grid renders, so the
     * image, the title and the credit line always describe a media the visitor can
     * actually see.
     */
    $live = (bool) ($live ?? false);
    $asset = is_array($asset ?? null) ? $asset : [];
    $hasPrevious = (bool) ($hasPrevious ?? false);
    $hasNext = (bool) ($hasNext ?? false);

    $assetTitle = trim((string) ($asset['name'] ?? ''));
    $assetAlt = trim((string) ($asset['alt'] ?? ''));
    $assetSrc = (string) ($asset['display'] ?? $asset['src'] ?? '');
    $assetSrcset = trim((string) ($asset['srcset'] ?? ''));
    $assetSizes = trim((string) ($asset['sizes'] ?? ''));
    $creditHtml = \Voodflow\Vmedia\Support\MediaExplorer::creditHtml($asset);

    $navClass = 'absolute top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl leading-none text-white transition';
    // Always present in the class list (enabled buttons ignore it) so the utility is
    // part of the compiled page stylesheet — the canvas preview compiles from this
    // same markup, and a class that only ever appeared in the live branch would never
    // be generated.
    $navDisabledClass = 'disabled:opacity-40';
@endphp

<div class="{{ $live ? '' : 'hidden' }}" data-vmedia-explorer-lightbox-root>
    <div
        class="vmedia-media-explorer__lightbox fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        data-vmedia-explorer-lightbox
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('vmedia::admin.editor.explorer_lightbox') }}"
        @if ($live)
            wire:click.self="closeMedia"
            wire:keydown.escape.window="closeMedia"
            wire:keydown.arrow-left.window="previousMedia"
            wire:keydown.arrow-right.window="nextMedia"
            x-init="$nextTick(() => $refs.vmediaExplorerClose.focus())"
            x-on:keydown.tab="
                const focusable = $el.querySelectorAll('button');
                if (focusable.length === 0) { return; }
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if ($event.shiftKey && document.activeElement === first) {
                    $event.preventDefault();
                    last.focus();
                } else if (! $event.shiftKey && document.activeElement === last) {
                    $event.preventDefault();
                    first.focus();
                }
            "
        @endif
    >
        <button
            type="button"
            @if ($live)
                x-ref="vmediaExplorerClose"
            @endif
            data-vmedia-explorer-close
            data-voodbuilder-skip-cta="true"
            @if ($live)
                wire:click="closeMedia"
            @else
                disabled
                aria-disabled="true"
            @endif
            class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-2xl leading-none text-white transition hover:bg-white/25"
            aria-label="{{ __('vmedia::admin.editor.close') }}"
        >&times;</button>

        <button
            type="button"
            data-voodbuilder-skip-cta="true"
            @if ($live)
                wire:click="previousMedia"
            @endif
            @disabled(! $live || ! $hasPrevious)
            class="{{ $navClass }} left-2 {{ $navDisabledClass }} hover:bg-white/25"
            aria-label="{{ __('vmedia::admin.editor.prev') }}"
        >&lsaquo;</button>

        <button
            type="button"
            data-voodbuilder-skip-cta="true"
            @if ($live)
                wire:click="nextMedia"
            @endif
            @disabled(! $live || ! $hasNext)
            class="{{ $navClass }} right-2 {{ $navDisabledClass }} hover:bg-white/25"
            aria-label="{{ __('vmedia::admin.editor.next') }}"
        >&rsaquo;</button>

        <figure class="flex max-h-[90vh] w-full max-w-5xl flex-col items-center gap-3">
            <img
                src="{{ $assetSrc }}"
                @if ($assetSrcset !== '') srcset="{{ $assetSrcset }}" sizes="{{ $assetSizes }}" @endif
                alt="{{ $assetAlt !== '' ? $assetAlt : $assetTitle }}"
                class="max-h-[78vh] w-auto max-w-full rounded-lg object-contain"
            />
            <figcaption class="text-center text-white">
                <p class="text-sm font-medium">{{ $assetTitle }}</p>
                {{-- Shown only when the media actually carries an attribution. --}}
                <p class="mt-1 text-xs text-white/60" @if ($creditHtml === '') hidden @endif>{!! $creditHtml !!}</p>
            </figcaption>
        </figure>
    </div>
</div>
