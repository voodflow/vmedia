@once
    @push('head')
        <style>
            .vmedia-gallery-dialog {
                padding: 0;
                border: none;
                background: transparent;
                margin: 0;
                max-width: none;
                width: 100vw;
                height: 100vh;
                overflow: visible;
            }
            .vmedia-gallery-dialog:not([open]) { display: none; }
            .vmedia-gallery-dialog[open] {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .vmedia-gallery-dialog::backdrop { background: rgba(15, 23, 42, 0.88); }
            .vmedia-gallery-dialog__viewport {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
                padding: clamp(1rem, 3vw, 2rem);
                box-sizing: border-box;
            }
            .vmedia-gallery-dialog__row {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: clamp(0.75rem, 2vw, 1.5rem);
                width: min(96vw, 1200px);
                max-height: 90vh;
            }
            .vmedia-gallery-dialog__stage {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                border-radius: 1rem;
                background: rgba(15, 23, 42, 0.35);
                box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
            }
            .vmedia-gallery-dialog__image {
                display: block;
                width: 100%;
                max-width: 100%;
                max-height: min(75vh, calc(90vh - 4rem));
                margin: 0 auto;
                object-fit: contain;
            }
            .vmedia-gallery-dialog__caption {
                margin: 0;
                padding: 0.875rem 1.25rem;
                text-align: center;
                font-size: 0.9375rem;
                line-height: 1.5;
                font-weight: 500;
                color: #fff;
                background: rgba(0, 0, 0, 0.78);
            }
            .vmedia-gallery-dialog__caption[hidden] { display: none; }
            .vmedia-gallery-dialog__close,
            .vmedia-gallery-dialog__nav {
                border: none;
                cursor: pointer;
                color: #fff;
            }
            .vmedia-gallery-dialog__close {
                position: absolute;
                top: clamp(0.75rem, 2vw, 1.25rem);
                right: clamp(0.75rem, 2vw, 1.25rem);
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 2.75rem;
                height: 2.75rem;
                border-radius: 999px;
                background: rgba(0, 0, 0, 0.55);
                font-size: 1.75rem;
                line-height: 1;
            }
            .vmedia-gallery-dialog__nav {
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 3rem;
                height: 3rem;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.95);
                font-size: 1.75rem;
                line-height: 1;
                color: #0f172a;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.querySelectorAll('[data-vmedia-gallery-lightbox]').forEach((root) => {
                if (root.__vmediaLightboxBound) {
                    return;
                }
                root.__vmediaLightboxBound = true;

                const dataEl = root.querySelector('[data-vmedia-gallery-data]');
                let dialog = root.querySelector('[data-vmedia-gallery-dialog]');
                if (!dataEl || !dialog) {
                    return;
                }
                if (dialog.parentElement !== document.body) {
                    document.body.appendChild(dialog);
                }

                const image = dialog.querySelector('[data-vmedia-gallery-image]');
                const caption = dialog.querySelector('[data-vmedia-gallery-caption]');
                const slides = JSON.parse(dataEl.textContent || '[]');
                let index = 0;

                const render = () => {
                    const slide = slides[index];
                    if (!slide || !image || !caption) {
                        return;
                    }
                    image.src = slide.url;
                    image.alt = slide.alt || '';
                    const text = slide.caption || slide.alt || '';
                    caption.textContent = text;
                    caption.hidden = text === '';
                };

                const open = (nextIndex) => {
                    index = nextIndex;
                    render();
                    if (typeof dialog.showModal === 'function') {
                        dialog.showModal();
                    }
                };

                root.querySelectorAll('[data-vmedia-gallery-index]').forEach((trigger) => {
                    trigger.addEventListener('click', () => open(Number(trigger.dataset.vmediaGalleryIndex || 0)));
                    trigger.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            open(Number(trigger.dataset.vmediaGalleryIndex || 0));
                        }
                    });
                });

                dialog.querySelector('[data-vmedia-gallery-close]')?.addEventListener('click', () => dialog.close());
                dialog.querySelector('[data-vmedia-gallery-prev]')?.addEventListener('click', () => {
                    index = (index - 1 + slides.length) % slides.length;
                    render();
                });
                dialog.querySelector('[data-vmedia-gallery-next]')?.addEventListener('click', () => {
                    index = (index + 1) % slides.length;
                    render();
                });
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) {
                        dialog.close();
                    }
                });
                dialog.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        index = (index - 1 + slides.length) % slides.length;
                        render();
                    }
                    if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        index = (index + 1) % slides.length;
                        render();
                    }
                });
            });
        </script>
    @endpush
@endonce

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
