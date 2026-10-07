@php
    /** @var array<string, mixed> $config */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string}> $slides */
    $speed = (int) ($config['speed'] ?? 40);
    $pause = (bool) ($config['pause_on_hover'] ?? true);
    $direction = (string) ($config['direction'] ?? 'left');
    $rounded = (bool) ($config['rounded'] ?? true);
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $radius = $rounded ? '0.875rem' : '0';
    $loop = array_merge($slides, $slides);
@endphp

@if ($empty)
    @include('vmedia::blocks.partials.empty', ['blockId' => $blockId])
@else
<section
    class="voodbuilder-editor-section vmedia-gallery-block"
    data-voodbuilder-block="{{ $blockId }}"
    @if ($lightbox) data-vmedia-gallery-lightbox @endif
>
    <div class="voodbuilder-editor-container w-full" data-voodbuilder-role="content" data-voodbuilder-content-width="full">
        @if ($heading)
            <h2 class="mb-6 px-5 text-2xl font-semibold tracking-tight text-vp-text-1 lg:px-8" data-voodbuilder-name="Heading">{{ $heading }}</h2>
        @endif

        <div
            class="vmedia-gallery-marquee"
            style="--vmedia-marquee-duration: {{ $speed }}s; --vmedia-radius: {{ $radius }}; --vmedia-marquee-direction: {{ $direction === 'right' ? 'reverse' : 'normal' }};"
            data-vmedia-pause="{{ $pause ? '1' : '0' }}"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            <div class="vmedia-gallery-marquee__fade vmedia-gallery-marquee__fade--left" aria-hidden="true"></div>
            <div class="vmedia-gallery-marquee__fade vmedia-gallery-marquee__fade--right" aria-hidden="true"></div>
            <div class="vmedia-gallery-marquee__track">
                @foreach ($loop as $index => $slide)
                    @php $realIndex = $index % count($slides); @endphp
                    <figure class="vmedia-gallery-marquee__item" data-voodbuilder-name="Gallery item">
                        @if ($lightbox)
                            <button
                                type="button"
                                class="vmedia-gallery-marquee__thumb"
                                data-vmedia-gallery-index="{{ $realIndex }}"
                                data-voodbuilder-skip-cta="true"
                                aria-label="{{ $slide['alt'] }}"
                            >
                                <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                            </button>
                        @else
                            <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy">
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading'))
    </div>
</section>

@once
<style>
    .vmedia-gallery-marquee {
        position: relative;
        overflow: hidden;
        padding-block: 0.25rem;
    }
    .vmedia-gallery-marquee__track {
        display: flex;
        width: max-content;
        gap: 1rem;
        animation: vmedia-marquee-scroll var(--vmedia-marquee-duration, 40s) linear infinite;
        animation-direction: var(--vmedia-marquee-direction, normal);
        will-change: transform;
    }
    .vmedia-gallery-marquee[data-vmedia-pause="1"]:hover .vmedia-gallery-marquee__track {
        animation-play-state: paused;
    }
    .vmedia-gallery-marquee__item {
        margin: 0;
        flex: 0 0 auto;
        width: min(22rem, 70vw);
    }
    .vmedia-gallery-marquee__thumb {
        display: block;
        width: 100%;
        padding: 0;
        border: none;
        background: transparent;
        cursor: zoom-in;
        overflow: hidden;
        border-radius: var(--vmedia-radius, 0.875rem);
    }
    .vmedia-gallery-marquee__thumb img,
    .vmedia-gallery-marquee__item > img {
        display: block;
        width: 100%;
        aspect-ratio: 5 / 4;
        object-fit: cover;
        border-radius: var(--vmedia-radius, 0.875rem);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        transition: transform 280ms ease;
    }
    .vmedia-gallery-marquee__thumb:hover img {
        transform: scale(1.03);
    }
    .vmedia-gallery-marquee__fade {
        pointer-events: none;
        position: absolute;
        top: 0;
        bottom: 0;
        width: 4rem;
        z-index: 1;
    }
    .vmedia-gallery-marquee__fade--left {
        left: 0;
        background: linear-gradient(to right, var(--color-vp-bg, #fff), transparent);
    }
    .vmedia-gallery-marquee__fade--right {
        right: 0;
        background: linear-gradient(to left, var(--color-vp-bg, #fff), transparent);
    }
    @keyframes vmedia-marquee-scroll {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .vmedia-gallery-marquee__track {
            animation: none;
            flex-wrap: wrap;
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endonce
@endif
