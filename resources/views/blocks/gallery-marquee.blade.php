@php
    /** @var array<string, mixed> $config */
    /** @var array<string, string|int> $ui */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string, uuid?: string, id?: int|null}> $slides */
    $speed = (int) ($config['speed'] ?? 40);
    $pause = (bool) ($config['pause_on_hover'] ?? true);
    $direction = (string) ($config['direction'] ?? 'left');
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $loop = array_merge($slides, $slides);
    $marqueeImage = trim(($ui['roundedImg'] ?? '').' block aspect-[5/4] w-full object-cover transition duration-300 hover:scale-[1.02]');
@endphp

@if ($empty)
    @include('vmedia::blocks.partials.empty', ['blockId' => $blockId])
@else
<section
    class="voodbuilder-editor-section vmedia-gallery-block"
    @if ($lightbox) data-vmedia-gallery-lightbox @endif
>
    <div class="voodbuilder-editor-container w-full" data-voodbuilder-role="content" data-voodbuilder-content-width="full">
        @if ($heading)
            <h2 class="mb-6 px-5 text-2xl font-semibold tracking-tight text-vp-text-1 lg:px-8" data-voodbuilder-name="Heading">{{ $heading }}</h2>
        @endif

        <div
            class="vmedia-gallery-marquee"
            style="--vmedia-marquee-duration: {{ $speed }}s; --vmedia-marquee-direction: {{ $direction === 'right' ? 'reverse' : 'normal' }};"
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
                        @include('vmedia::blocks.partials.slide-frame', [
                            'slide' => $slide,
                            'ui' => array_merge($ui, [
                                'thumb' => trim(($ui['thumb'] ?? '').' shadow-lg'),
                            ]),
                            'index' => $realIndex,
                            'lightbox' => $lightbox,
                            'showCaptions' => false,
                            'captionPosition' => 'below',
                            'imageClass' => $marqueeImage,
                        ])
                    </figure>
                @endforeach
            </div>
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading'))
    </div>
</section>
@endif
