@php
    use Voodflow\Vmedia\Support\CreditsHtml;

    /** @var array{url?: string, thumb?: string, alt?: string, caption?: string, credits?: string} $slide */
    $credits = trim((string) ($slide['credits'] ?? ''));
    $creditsHtml = CreditsHtml::sanitize($credits);
    $onDark = (bool) ($onDark ?? false);
@endphp

@if ($creditsHtml !== '')
    <div class="vmedia-gallery-credits{{ $onDark ? ' vmedia-gallery-credits--on-dark' : '' }}" data-vmedia-credits>
        <button
            type="button"
            class="vmedia-gallery-credits__btn{{ $onDark ? ' vmedia-gallery-credits__btn--on-dark' : '' }}"
            data-vmedia-credits-toggle
            data-voodbuilder-skip-cta="true"
            aria-expanded="false"
            aria-label="{{ __('vmedia::admin.editor.credits') }}"
            title="{{ __('vmedia::admin.editor.credits') }}"
        >
            <span aria-hidden="true">i</span>
        </button>
        <div class="vmedia-gallery-credits__panel" data-vmedia-credits-panel hidden role="note">
            {!! $creditsHtml !!}
        </div>
    </div>
@endif
