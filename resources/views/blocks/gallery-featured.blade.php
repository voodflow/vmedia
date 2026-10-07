@php
    /** @var array<string, mixed> $config */
    /** @var array<string, string|int> $ui */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string, credits?: string, uuid?: string, id?: int|null}> $slides */
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $captionPosition = (string) ($config['caption_position'] ?? 'below');
    $captionBg = (string) ($config['caption_bg'] ?? 'rgba(0, 0, 0, 0.72)');
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $featured = $slides[0] ?? null;
    $rest = array_slice($slides, 1);
    $columns = (int) ($ui['columns'] ?? $config['columns'] ?? 4);
    $stackGap = match ($config['gap'] ?? 'md') {
        'sm' => 'gap-2',
        'lg' => 'gap-6',
        default => 'gap-4',
    };
    $featuredImage = trim(($ui['roundedImg'] ?? '').' block aspect-[21/9] w-full object-cover transition duration-500 hover:scale-[1.02] sm:aspect-video');
@endphp

@if ($empty)
    @include('vmedia::blocks.partials.empty', ['blockId' => $blockId])
@else
<section
    class="voodbuilder-editor-section vmedia-gallery-block"
    @if ($lightbox) data-vmedia-gallery-lightbox @endif
>
    <div class="voodbuilder-editor-container w-full" data-voodbuilder-role="content" data-voodbuilder-content-width="wide">
        @if ($heading)
            <h2 class="mb-6 text-2xl font-semibold tracking-tight text-vp-text-1" data-voodbuilder-name="Heading">{{ $heading }}</h2>
        @endif

        <div
            class="grid {{ $stackGap }}"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @if ($featured)
                <figure class="vmedia-gallery-item m-0" data-voodbuilder-name="Featured image">
                    @if ($captionPosition === 'above')
                        @include('vmedia::blocks.partials.slide-caption', [
                            'slide' => $featured,
                            'showCaptions' => $showCaptions,
                            'captionPosition' => $captionPosition,
                            'captionBg' => $captionBg,
                        ])
                    @endif

                    @include('vmedia::blocks.partials.slide-frame', [
                        'slide' => $featured,
                        'ui' => $ui,
                        'index' => 0,
                        'lightbox' => $lightbox,
                        'showCaptions' => $showCaptions,
                        'captionPosition' => $captionPosition === 'below' ? 'overlay' : $captionPosition,
                        'captionBg' => $captionBg,
                        'imageClass' => $featuredImage,
                        'src' => $featured['url'] ?? $featured['thumb'] ?? '',
                        'loading' => 'eager',
                    ])
                </figure>
            @endif

            @if ($rest !== [])
                <div
                    class="{{ $ui['featuredRow'] }}"
                    style="--vmedia-columns: {{ $columns }};"
                    data-vmedia-columns="{{ $columns }}"
                >
                    @foreach ($rest as $offset => $slide)
                        @php $index = $offset + 1; @endphp
                        <figure class="vmedia-gallery-item m-0 min-w-0" data-voodbuilder-name="Gallery item">
                            @if ($captionPosition === 'above')
                                @include('vmedia::blocks.partials.slide-caption', compact('slide', 'showCaptions', 'captionPosition', 'captionBg'))
                            @endif

                            @include('vmedia::blocks.partials.slide-frame', compact(
                                'slide', 'ui', 'index', 'lightbox', 'showCaptions', 'captionPosition', 'captionBg'
                            ))

                            @if ($captionPosition === 'below')
                                @include('vmedia::blocks.partials.slide-caption', compact('slide', 'showCaptions', 'captionPosition', 'captionBg'))
                            @endif
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading', 'captionPosition', 'captionBg'))
    </div>
</section>
@endif
