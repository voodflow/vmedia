@php
    /** @var array<string, mixed> $config */
    /** @var array<string, string> $ui */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string}> $slides */
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
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
            class="{{ $ui['grid'] }}"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @foreach ($slides as $index => $slide)
                <figure class="m-0 min-w-0" data-voodbuilder-name="Gallery item">
                    @if ($lightbox)
                        <button
                            type="button"
                            class="{{ $ui['thumb'] }}"
                            data-vmedia-gallery-index="{{ $index }}"
                            data-voodbuilder-skip-cta="true"
                            aria-label="{{ $slide['alt'] }}"
                        >
                            <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="{{ $ui['image'] }}">
                        </button>
                    @else
                        <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="{{ $ui['image'] }}">
                    @endif
                    @if ($showCaptions && filled($slide['caption'] ?? null))
                        <figcaption class="mt-2 text-sm text-vp-text-2">{{ $slide['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading'))
    </div>
</section>
@endif
