@php
    /** @var array<string, mixed> $config */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string}> $slides */
    $columns = (int) ($config['columns'] ?? 4);
    $gap = (string) ($config['gap'] ?? 'md');
    $rounded = (bool) ($config['rounded'] ?? true);
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $gapMap = ['sm' => '0.5rem', 'md' => '1rem', 'lg' => '1.5rem'];
    $gapCss = $gapMap[$gap] ?? '1rem';
    $radius = $rounded ? '1rem' : '0';
    $featured = $slides[0] ?? null;
    $rest = array_slice($slides, 1);
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
            class="vmedia-gallery-featured"
            style="--vmedia-cols: {{ $columns }}; --vmedia-gap: {{ $gapCss }}; --vmedia-radius: {{ $radius }};"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @if ($featured)
                <figure class="vmedia-gallery-featured__hero" data-voodbuilder-name="Featured image">
                    @if ($lightbox)
                        <button
                            type="button"
                            class="vmedia-gallery-featured__hero-btn"
                            data-vmedia-gallery-index="0"
                            data-voodbuilder-skip-cta="true"
                            aria-label="{{ $featured['alt'] }}"
                        >
                            <img src="{{ $featured['url'] }}" alt="{{ $featured['alt'] }}" loading="eager">
                            <span class="vmedia-gallery-featured__hero-shade"></span>
                        </button>
                    @else
                        <img src="{{ $featured['url'] }}" alt="{{ $featured['alt'] }}" loading="eager">
                    @endif
                    @if ($showCaptions && filled($featured['caption'] ?? null))
                        <figcaption class="vmedia-gallery-featured__hero-caption">{{ $featured['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($rest !== [])
                <div class="vmedia-gallery-featured__row">
                    @foreach ($rest as $offset => $slide)
                        @php $index = $offset + 1; @endphp
                        <figure class="vmedia-gallery-featured__item" data-voodbuilder-name="Gallery item">
                            @if ($lightbox)
                                <button
                                    type="button"
                                    class="vmedia-gallery-featured__thumb"
                                    data-vmedia-gallery-index="{{ $index }}"
                                    data-voodbuilder-skip-cta="true"
                                    aria-label="{{ $slide['alt'] }}"
                                >
                                    <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                                </button>
                            @else
                                <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                            @endif
                            @if ($showCaptions && filled($slide['caption'] ?? null))
                                <figcaption class="vmedia-gallery-featured__caption">{{ $slide['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading'))
    </div>
</section>

@once
<style>
    .vmedia-gallery-featured {
        display: grid;
        gap: var(--vmedia-gap, 1rem);
    }
    .vmedia-gallery-featured__hero {
        margin: 0;
        position: relative;
        overflow: hidden;
        border-radius: var(--vmedia-radius, 1rem);
    }
    .vmedia-gallery-featured__hero-btn {
        display: block;
        width: 100%;
        padding: 0;
        border: none;
        background: transparent;
        cursor: zoom-in;
        position: relative;
        overflow: hidden;
        border-radius: var(--vmedia-radius, 1rem);
    }
    .vmedia-gallery-featured__hero img,
    .vmedia-gallery-featured__hero-btn img {
        display: block;
        width: 100%;
        aspect-ratio: 21 / 9;
        object-fit: cover;
        transition: transform 400ms ease;
    }
    .vmedia-gallery-featured__hero-btn:hover img {
        transform: scale(1.03);
    }
    .vmedia-gallery-featured__hero-shade {
        pointer-events: none;
        position: absolute;
        inset: auto 0 0 0;
        height: 45%;
        background: linear-gradient(to top, rgba(15, 23, 42, 0.55), transparent);
    }
    .vmedia-gallery-featured__hero-caption {
        position: absolute;
        left: 1.25rem;
        bottom: 1.25rem;
        margin: 0;
        color: #fff;
        font-size: 1rem;
        font-weight: 600;
        text-shadow: 0 1px 8px rgba(0,0,0,0.35);
    }
    .vmedia-gallery-featured__row {
        display: grid;
        gap: var(--vmedia-gap, 1rem);
        grid-template-columns: repeat(var(--vmedia-cols, 4), minmax(0, 1fr));
    }
    .vmedia-gallery-featured__item { margin: 0; min-width: 0; }
    .vmedia-gallery-featured__thumb {
        display: block;
        width: 100%;
        padding: 0;
        border: none;
        background: transparent;
        cursor: zoom-in;
        overflow: hidden;
        border-radius: calc(var(--vmedia-radius, 1rem) * 0.75);
    }
    .vmedia-gallery-featured__thumb img,
    .vmedia-gallery-featured__item > img {
        display: block;
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        border-radius: calc(var(--vmedia-radius, 1rem) * 0.75);
        transition: transform 280ms ease, filter 280ms ease;
    }
    .vmedia-gallery-featured__thumb:hover img {
        transform: scale(1.04);
        filter: brightness(1.05);
    }
    .vmedia-gallery-featured__caption {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: var(--color-vp-text-2, #64748b);
    }
    @media (max-width: 900px) {
        .vmedia-gallery-featured__hero img,
        .vmedia-gallery-featured__hero-btn img { aspect-ratio: 16 / 9; }
        .vmedia-gallery-featured__row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
        .vmedia-gallery-featured__row { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endonce
@endif
