@php
    /** @var array<string, mixed> $config */
    /** @var array<string, string> $ui */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string, credits?: string}> $slides */
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $captionPosition = (string) ($config['caption_position'] ?? 'below');
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $featured = $slides[0] ?? null;
    $rest = array_slice($slides, 1);
    $stackGap = match ($config['gap'] ?? 'md') {
        'sm' => 'gap-2',
        'lg' => 'gap-6',
        default => 'gap-4',
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
            class="grid {{ $stackGap }}"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @if ($featured)
                <figure class="relative m-0 overflow-hidden {{ $ui['rounded'] }}" data-voodbuilder-name="Featured image">
                    @if ($captionPosition === 'above')
                        @include('vmedia::blocks.partials.slide-caption', [
                            'slide' => $featured,
                            'showCaptions' => $showCaptions,
                            'captionPosition' => $captionPosition,
                        ])
                    @endif

                    @if ($lightbox)
                        <button
                            type="button"
                            class="{{ $ui['thumb'] }} relative"
                            data-vmedia-gallery-index="0"
                            data-voodbuilder-skip-cta="true"
                            aria-label="{{ $featured['alt'] }}"
                        >
                            <img
                                src="{{ $featured['url'] }}"
                                alt="{{ $featured['alt'] }}"
                                loading="eager"
                                class="{{ $ui['roundedImg'] }} block aspect-[21/9] w-full object-cover transition duration-500 hover:scale-[1.02] sm:aspect-video"
                            >
                            <span class="pointer-events-none absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-zinc-950/60 to-transparent" aria-hidden="true"></span>
                        </button>
                    @else
                        <img
                            src="{{ $featured['url'] }}"
                            alt="{{ $featured['alt'] }}"
                            loading="eager"
                            class="{{ $ui['roundedImg'] }} block aspect-[21/9] w-full object-cover sm:aspect-video"
                        >
                    @endif

                    @if ($captionPosition !== 'above' && $showCaptions && filled($featured['caption'] ?? null))
                        <figcaption class="absolute bottom-4 left-4 m-0 max-w-[calc(100%-5rem)] text-base font-semibold text-white drop-shadow">{{ $featured['caption'] }}</figcaption>
                    @endif
                    @if ($captionPosition !== 'above' && filled($featured['credits'] ?? null))
                        <details class="vmedia-gallery-credits absolute bottom-4 right-4 z-10">
                            <summary
                                class="vmedia-gallery-credits__btn vmedia-gallery-credits__btn--on-dark"
                                data-voodbuilder-skip-cta="true"
                                aria-label="{{ __('vmedia::admin.editor.credits') }}"
                                title="{{ __('vmedia::admin.editor.credits') }}"
                            >
                                <span aria-hidden="true">i</span>
                            </summary>
                            <div class="vmedia-gallery-credits__panel vmedia-gallery-credits__panel--up" role="note">
                                {{ $featured['credits'] }}
                            </div>
                        </details>
                    @endif
                </figure>
            @endif

            @if ($rest !== [])
                <div class="{{ $ui['featuredRow'] }}">
                    @foreach ($rest as $offset => $slide)
                        @php $index = $offset + 1; @endphp
                        <figure class="m-0 min-w-0" data-voodbuilder-name="Gallery item">
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
                                    <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="{{ $ui['image'] }}">
                                </button>
                            @else
                                <img src="{{ $slide['thumb'] }}" alt="{{ $slide['alt'] }}" loading="lazy" class="{{ $ui['image'] }}">
                            @endif

                            @if ($captionPosition !== 'above')
                                @include('vmedia::blocks.partials.slide-caption', compact('slide', 'showCaptions', 'captionPosition'))
                            @endif
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading', 'captionPosition'))
    </div>
</section>
@endif
