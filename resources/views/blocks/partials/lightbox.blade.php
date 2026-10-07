@php
    $captionPosition = (string) ($captionPosition ?? 'below');
    $captionBg = (string) ($captionBg ?? 'rgba(0, 0, 0, 0.72)');
@endphp

@if ($lightbox ?? false)
    <dialog
        class="vmedia-gallery-dialog"
        data-vmedia-gallery-dialog
        data-vmedia-caption-position="{{ $captionPosition }}"
        style="--vmedia-caption-bg: {{ $captionBg }}"
        data-voodbuilder-skip-cta="true"
        aria-label="{{ $heading ?? __('vmedia::admin.editor.gallery') }}"
    >
        <div class="vmedia-gallery-dialog__viewport" data-vmedia-gallery-backdrop>
            <button type="button" class="vmedia-gallery-dialog__close" data-vmedia-gallery-close data-voodbuilder-skip-cta="true" aria-label="{{ __('vmedia::admin.editor.close') }}">&times;</button>
            <div class="vmedia-gallery-dialog__row">
                <button type="button" class="vmedia-gallery-dialog__nav" data-vmedia-gallery-prev data-voodbuilder-skip-cta="true" aria-label="{{ __('vmedia::admin.editor.prev') }}">&lsaquo;</button>
                <div class="vmedia-gallery-dialog__stage">
                    <p class="vmedia-gallery-dialog__caption" data-vmedia-gallery-caption data-vmedia-gallery-caption-above hidden></p>
                    <div class="vmedia-gallery-dialog__media">
                        <img src="" alt="" class="vmedia-gallery-dialog__image" data-vmedia-gallery-image>
                        <div class="vmedia-gallery-credits vmedia-gallery-credits--on-dark vmedia-gallery-dialog__credits" data-vmedia-credits data-vmedia-gallery-credits hidden>
                            <button
                                type="button"
                                class="vmedia-gallery-credits__btn vmedia-gallery-credits__btn--on-dark"
                                data-vmedia-credits-toggle
                                data-voodbuilder-skip-cta="true"
                                aria-expanded="false"
                                aria-label="{{ __('vmedia::admin.editor.credits') }}"
                                title="{{ __('vmedia::admin.editor.credits') }}"
                            >
                                <span aria-hidden="true">i</span>
                            </button>
                            <div class="vmedia-gallery-credits__panel" data-vmedia-credits-panel data-vmedia-gallery-credits-text hidden role="note"></div>
                        </div>
                    </div>
                    <p class="vmedia-gallery-dialog__caption" data-vmedia-gallery-caption data-vmedia-gallery-caption-below hidden></p>
                </div>
                <button type="button" class="vmedia-gallery-dialog__nav" data-vmedia-gallery-next data-voodbuilder-skip-cta="true" aria-label="{{ __('vmedia::admin.editor.next') }}">&rsaquo;</button>
            </div>
        </div>
    </dialog>
    {{-- Not <script>: EditorDynamicBlockRenderer / DOMDocument strip script nodes on public hydrate. --}}
    <div hidden data-vmedia-gallery-data>@json($slides)</div>
@endif
