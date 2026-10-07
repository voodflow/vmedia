@php
    /** @var array<string, mixed> $config */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string}> $slides */
    $columns = (int) ($config['columns'] ?? 3);
    $gap = (string) ($config['gap'] ?? 'md');
    $aspect = (string) ($config['aspect'] ?? '4/3');
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
            class="vmedia-gallery-grid"
            style="--vmedia-cols: {{ $columns }}; --vmedia-gap: {{ $gapCss }}; --vmedia-radius: {{ $radius }}; --vmedia-aspect: {{ $aspect === 'auto' ? 'auto' : $aspect }};"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @foreach ($slides as $index => $slide)
                <figure class="vmedia-gallery-grid__item" data-voodbuilder-name="Gallery item">
                    @if ($lightbox)
                        <button
                            type="button"
                            class="vmedia-gallery-grid__thumb"
                            data-vmedia-gallery-index="{{ $index }}"
                            data-voodbuilder-skip-cta="true"
                            aria-label="{{ $slide['alt'] }}"
                        >
                            <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                        </button>
                    @else
                        <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="vmedia-gallery-grid__img">
                    @endif
                    @if ($showCaptions && filled($slide['caption'] ?? null))
                        <figcaption class="vmedia-gallery-grid__caption">{{ $slide['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading'))
    </div>
</section>

@once
<style>
    .vmedia-gallery-grid {
        display: grid;
        gap: var(--vmedia-gap, 1rem);
        grid-template-columns: repeat(var(--vmedia-cols, 3), minmax(0, 1fr));
    }
    .vmedia-gallery-grid__item { margin: 0; min-width: 0; }
    .vmedia-gallery-grid__thumb {
        display: block;
        width: 100%;
        padding: 0;
        border: none;
        background: transparent;
        cursor: zoom-in;
        overflow: hidden;
        border-radius: var(--vmedia-radius, 0.875rem);
    }
    .vmedia-gallery-grid__thumb img,
    .vmedia-gallery-grid__img {
        display: block;
        width: 100%;
        aspect-ratio: var(--vmedia-aspect, 4 / 3);
        object-fit: cover;
        border-radius: var(--vmedia-radius, 0.875rem);
        transition: transform 280ms ease, filter 280ms ease;
    }
    .vmedia-gallery-grid__thumb:hover img,
    .vmedia-gallery-grid__item:hover .vmedia-gallery-grid__img {
        transform: scale(1.03);
        filter: brightness(1.05);
    }
    .vmedia-gallery-grid__caption {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: var(--color-vp-text-2, #64748b);
    }
    @media (max-width: 768px) {
        .vmedia-gallery-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
        .vmedia-gallery-grid { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endonce
@endif
