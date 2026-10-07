@php
    /** @var array<string, mixed> $config */
    /** @var array<string, string|int> $ui */
    /** @var list<array{url: string, thumb: string, alt: string, caption: string, credits?: string, uuid?: string, id?: int|null}> $slides */
    $showCaptions = (bool) ($config['show_captions'] ?? false);
    $captionPosition = (string) ($config['caption_position'] ?? 'below');
    $captionBg = (string) ($config['caption_bg'] ?? 'rgba(0, 0, 0, 0.72)');
    $lightbox = (bool) ($config['lightbox'] ?? true);
    $heading = $config['heading'] ?? null;
    $columns = (int) ($ui['columns'] ?? $config['columns'] ?? 3);
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
            style="--vmedia-columns: {{ $columns }};"
            data-vmedia-columns="{{ $columns }}"
            data-voodbuilder-role="gallery"
            data-voodbuilder-name="Gallery"
        >
            @foreach ($slides as $index => $slide)
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

        @include('vmedia::blocks.partials.lightbox', compact('lightbox', 'slides', 'heading', 'captionPosition', 'captionBg'))
    </div>
</section>
@endif
