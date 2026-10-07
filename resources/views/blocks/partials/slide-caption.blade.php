@php
    /** @var array{url?: string, thumb?: string, alt?: string, caption?: string, credits?: string} $slide */
    $captionAbove = ($captionPosition ?? 'below') === 'above';
    $showCaption = ($showCaptions ?? false) && filled($slide['caption'] ?? null);
    $captionBg = (string) ($captionBg ?? 'rgba(0, 0, 0, 0.72)');
@endphp

@if ($showCaption)
    <figcaption
        class="vmedia-gallery-item__caption {{ $captionAbove ? 'vmedia-gallery-item__caption--above' : 'vmedia-gallery-item__caption--below' }}"
        @if ($captionAbove)
            style="--vmedia-caption-bg: {{ $captionBg }}"
        @endif
    >{{ $slide['caption'] }}</figcaption>
@endif
