@php
    /** @var array<string, mixed> $config */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string}> $slides */
    $columns = (int) ($config['columns'] ?? 3);
    $gap = (string) ($config['gap'] ?? 'md');
    $rounded = (bool) ($config['rounded'] ?? true);
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $gapMap = ['sm' => '0.5rem', 'md' => '1rem', 'lg' => '1.5rem'];
    $gapCss = $gapMap[$gap] ?? '1rem';
    $radius = $rounded ? '0.875rem' : '0';
@endphp

@if ($empty)
    @include('vmedia::blocks.partials.empty', ['blockId' => $blockId])
@else
<section
    class="voodbuilder-editor-section vmedia-gallery-block"
    data-voodbuilder-block="{{ $blockId }}"
    @if ($lightbox) data-vmedia-gallery-lightbox @endif
>
    <div class="voodbuilder-editor-container w-full" data-voodbuilder-role="content" data-voodbuilder-content-width="wide">
        @if ($heading)
            <h2 class="mb-6 text-2xl font-semibold tracking-tight text-vp-text-1" data-voodbuilder-name="Heading">{{ $heading }}</h2>
        @endif

        <div
            class="vmedia-gallery-masonry"
            style="--vmedia-cols: {{ $columns }}; --vmedia-gap: {{ $gapCss }}; --vmedia-radius: {{ $radius }};"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @foreach ($slides as $index => $slide)
                <figure class="vmedia-gallery-masonry__item" data-voodbuilder-name="Gallery item">
                    @if ($lightbox)
                        <button
                            type="button"
                            class="vmedia-gallery-masonry__thumb"
                            data-vmedia-gallery-index="{{ $index }}"
                            data-voodbuilder-skip-cta="true"
                            aria-label="{{ $slide['alt'] }}"
                        >
                            <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                        </button>
                    @else
                        <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="vmedia-gallery-masonry__img">
                    @endif
                    @if ($showCaptions && filled($slide['caption'] ?? null))
                        <figcaption class="vmedia-gallery-masonry__caption">{{ $slide['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading'))
    </div>
</section>

@once
<style>
    .vmedia-gallery-masonry {
        columns: var(--vmedia-cols, 3);
        column-gap: var(--vmedia-gap, 1rem);
    }
    .vmedia-gallery-masonry__item {
        break-inside: avoid;
        margin: 0 0 var(--vmedia-gap, 1rem);
    }
    .vmedia-gallery-masonry__thumb {
        display: block;
        width: 100%;
        padding: 0;
        border: none;
        background: transparent;
        cursor: zoom-in;
        overflow: hidden;
        border-radius: var(--vmedia-radius, 0.875rem);
    }
    .vmedia-gallery-masonry__thumb img,
    .vmedia-gallery-masonry__img {
        display: block;
        width: 100%;
        height: auto;
        border-radius: var(--vmedia-radius, 0.875rem);
        transition: filter 280ms ease, transform 280ms ease;
    }
    .vmedia-gallery-masonry__thumb:hover img {
        filter: brightness(1.06);
        transform: translateY(-2px);
    }
    .vmedia-gallery-masonry__caption {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: var(--color-vp-text-2, #64748b);
    }
    @media (max-width: 768px) {
        .vmedia-gallery-masonry { columns: 2; }
    }
    @media (max-width: 480px) {
        .vmedia-gallery-masonry { columns: 1; }
    }
</style>
@endonce
@endif
