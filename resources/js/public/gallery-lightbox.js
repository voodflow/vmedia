/**
 * Public lightbox for VoodMedia gallery blocks (dialog + prev/next).
 * Loaded on VoodBuilder layouts — not via @push from block HTML (Grapes/DOM hydrate drop scripts).
 */
(function () {
    'use strict';

    function bindRoot(root) {
        if (! root || root.__vmediaLightboxBound) {
            return;
        }
        root.__vmediaLightboxBound = true;

        var dataEl = root.querySelector('[data-vmedia-gallery-data]');
        var dialog = root.querySelector('[data-vmedia-gallery-dialog]');
        if (! dataEl || ! dialog) {
            return;
        }

        if (dialog.parentElement !== document.body) {
            document.body.appendChild(dialog);
        }

        var image = dialog.querySelector('[data-vmedia-gallery-image]');
        var caption = dialog.querySelector('[data-vmedia-gallery-caption]');
        var slides = [];
        try {
            slides = JSON.parse(dataEl.textContent || '[]');
        } catch (err) {
            slides = [];
        }
        if (! Array.isArray(slides) || slides.length === 0 || ! image) {
            return;
        }

        var index = 0;

        function render() {
            var slide = slides[index];
            if (! slide) {
                return;
            }
            image.src = slide.url || slide.thumb || '';
            image.alt = slide.alt || '';
            if (caption) {
                var text = slide.caption || slide.alt || '';
                caption.textContent = text;
                caption.hidden = text === '';
            }
        }

        function open(nextIndex) {
            index = nextIndex;
            render();
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        }

        root.querySelectorAll('[data-vmedia-gallery-index]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                open(Number(trigger.getAttribute('data-vmedia-gallery-index') || 0));
            });
            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open(Number(trigger.getAttribute('data-vmedia-gallery-index') || 0));
                }
            });
        });

        var closeBtn = dialog.querySelector('[data-vmedia-gallery-close]');
        var prevBtn = dialog.querySelector('[data-vmedia-gallery-prev]');
        var nextBtn = dialog.querySelector('[data-vmedia-gallery-next]');

        closeBtn && closeBtn.addEventListener('click', function () { dialog.close(); });
        prevBtn && prevBtn.addEventListener('click', function () {
            index = (index - 1 + slides.length) % slides.length;
            render();
        });
        nextBtn && nextBtn.addEventListener('click', function () {
            index = (index + 1) % slides.length;
            render();
        });
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
        dialog.addEventListener('keydown', function (event) {
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
    }

    function boot() {
        document.querySelectorAll('[data-vmedia-gallery-lightbox]').forEach(bindRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
