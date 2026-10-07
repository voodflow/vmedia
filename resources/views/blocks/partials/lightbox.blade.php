@if ($lightbox ?? false)
    <dialog
        class="vmedia-gallery-dialog"
        data-vmedia-gallery-dialog
        data-voodbuilder-skip-cta="true"
        aria-label="{{ $heading ?? __('vmedia::admin.editor.gallery') }}"
    >
        <div class="vmedia-gallery-dialog__viewport">
            <button type="button" class="vmedia-gallery-dialog__close" data-vmedia-gallery-close data-voodbuilder-skip-cta="true" aria-label="{{ __('vmedia::admin.editor.close') }}">&times;</button>
            <div class="vmedia-gallery-dialog__row">
                <button type="button" class="vmedia-gallery-dialog__nav" data-vmedia-gallery-prev data-voodbuilder-skip-cta="true" aria-label="{{ __('vmedia::admin.editor.prev') }}">&lsaquo;</button>
                <div class="vmedia-gallery-dialog__stage">
                    <img src="" alt="" class="vmedia-gallery-dialog__image" data-vmedia-gallery-image>
                    <p class="vmedia-gallery-dialog__caption" data-vmedia-gallery-caption hidden></p>
                </div>
                <button type="button" class="vmedia-gallery-dialog__nav" data-vmedia-gallery-next data-voodbuilder-skip-cta="true" aria-label="{{ __('vmedia::admin.editor.next') }}">&rsaquo;</button>
            </div>
        </div>
    </dialog>
    <script type="application/json" data-vmedia-gallery-data>@json($slides)</script>
@endif
