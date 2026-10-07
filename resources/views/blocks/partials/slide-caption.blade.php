@php
    /** @var array{url?: string, thumb?: string, alt?: string, caption?: string, credits?: string} $slide */
    $captionAbove = ($captionPosition ?? 'below') === 'above';
    $showCaption = ($showCaptions ?? false) && filled($slide['caption'] ?? null);
    $credits = trim((string) ($slide['credits'] ?? ''));
    $showCredits = $credits !== '';
@endphp

@if ($showCaption || $showCredits)
    <div class="{{ $captionAbove ? 'mb-2' : 'mt-2' }} flex items-start justify-between gap-2">
        @if ($showCaption)
            <figcaption class="min-w-0 flex-1 text-sm text-vp-text-2">{{ $slide['caption'] }}</figcaption>
        @else
            <span class="min-w-0 flex-1"></span>
        @endif
        @if ($showCredits)
            <details class="vmedia-gallery-credits relative shrink-0">
                <summary
                    class="vmedia-gallery-credits__btn"
                    data-voodbuilder-skip-cta="true"
                    onclick="event.stopPropagation()"
                    aria-label="{{ __('vmedia::admin.editor.credits') }}"
                    title="{{ __('vmedia::admin.editor.credits') }}"
                >
                    <span aria-hidden="true">i</span>
                </summary>
                <div class="vmedia-gallery-credits__panel" role="note">
                    {{ $credits }}
                </div>
            </details>
        @endif
    </div>
@endif
