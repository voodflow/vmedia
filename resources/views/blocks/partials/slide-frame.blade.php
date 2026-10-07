@php
    /** @var array{url?: string, thumb?: string, alt?: string, caption?: string, credits?: string, uuid?: string, id?: int|null} $slide */
    /** @var array<string, string> $ui */
    $index = (int) ($index ?? 0);
    $lightbox = (bool) ($lightbox ?? true);
    $showCaptions = (bool) ($showCaptions ?? false);
    $captionPosition = (string) ($captionPosition ?? 'below');
    $captionBg = (string) ($captionBg ?? 'rgba(0, 0, 0, 0.72)');
    $imageClass = (string) ($imageClass ?? $ui['image']);
    $src = (string) ($src ?? ($slide['thumb'] ?? $slide['url'] ?? ''));
    $uuid = trim((string) ($slide['uuid'] ?? ''));
    $mediaId = $slide['id'] ?? null;
    $showOverlayCaption = $showCaptions
        && $captionPosition === 'overlay'
        && filled($slide['caption'] ?? null);
@endphp

<div class="vmedia-gallery-item__frame relative overflow-hidden {{ $ui['rounded'] }}">
    @if ($lightbox)
        <button
            type="button"
            class="{{ $ui['thumb'] }}"
            data-vmedia-gallery-index="{{ $index }}"
            data-voodbuilder-skip-cta="true"
            aria-label="{{ $slide['alt'] ?? '' }}"
        >
            <img
                src="{{ $src }}"
                alt="{{ $slide['alt'] ?? '' }}"
                loading="{{ $loading ?? 'lazy' }}"
                class="{{ $imageClass }}"
                @if ($uuid !== '') data-vb-media-uuid="{{ $uuid }}" @endif
                @if (is_numeric($mediaId)) data-vb-media-id="{{ (int) $mediaId }}" @endif
            >
        </button>
    @else
        <img
            src="{{ $src }}"
            alt="{{ $slide['alt'] ?? '' }}"
            loading="{{ $loading ?? 'lazy' }}"
            class="{{ $imageClass }}"
            @if ($uuid !== '') data-vb-media-uuid="{{ $uuid }}" @endif
            @if (is_numeric($mediaId)) data-vb-media-id="{{ (int) $mediaId }}" @endif
        >
    @endif

    @if ($showOverlayCaption)
        <figcaption
            class="vmedia-gallery-item__caption vmedia-gallery-item__caption--overlay"
            style="--vmedia-caption-bg: {{ $captionBg }}"
        >{{ $slide['caption'] }}</figcaption>
    @endif

    @include('vmedia::blocks.partials.slide-credits', [
        'slide' => $slide,
        'onDark' => true,
    ])
</div>
