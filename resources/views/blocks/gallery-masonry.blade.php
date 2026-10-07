@php
    /** @var array<string, mixed> $config */
    /** @var array<string, string> $ui */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string, credits?: string}> $slides */
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $captionPosition = (string) ($config['caption_position'] ?? 'below');
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $itemGap = match ($config['gap'] ?? 'md') {
        'sm' => 'mb-2',
        'lg' => 'mb-6',
        default => 'mb-4',
    };
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
            class="{{ $ui['masonry'] }}"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @foreach ($slides as $index => $slide)
                <figure class="{{ $itemGap }} break-inside-avoid" data-voodbuilder-name="Gallery item">
                    @if ($captionPosition === 'above')
                        @include('vmedia::blocks.partials.slide-caption', compact('slide', 'showCaptions', 'captionPosition'))
                    @endif

                    @if ($lightbox)
                        <button
                            type="button"
                            class="{{ $ui['thumb'] }}"
                            data-vmedia-gallery-index="{{ $index }}"
                            data-voodbuilder-skip-cta="true"
                            aria-label="{{ $slide['alt'] }}"
                        >
                            <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="{{ $ui['roundedImg'] }} block h-auto w-full object-cover transition duration-300 hover:brightness-105">
                        </button>
                    @else
                        <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="{{ $ui['roundedImg'] }} block h-auto w-full object-cover">
                    @endif

                    @if ($captionPosition !== 'above')
                        @include('vmedia::blocks.partials.slide-caption', compact('slide', 'showCaptions', 'captionPosition'))
                    @endif
                </figure>
            @endforeach
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading', 'captionPosition'))
    </div>
</section>
@endif
