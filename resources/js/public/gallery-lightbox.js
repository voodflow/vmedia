/**
 * Public lightbox + credits popovers for VoodMedia gallery blocks.
 * Loaded on VoodBuilder layouts — not via @push from block HTML (Grapes/DOM hydrate drop scripts).
 */
(function () {
    'use strict';

    function closeCredits(root) {
        if (! root) {
            return;
        }
        var panel = root.querySelector('[data-vmedia-credits-panel]');
        var toggle = root.querySelector('[data-vmedia-credits-toggle]');
        if (panel) {
            panel.hidden = true;
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function openCredits(root) {
        if (! root) {
            return;
        }
        document.querySelectorAll('[data-vmedia-credits]').forEach(function (other) {
            if (other !== root) {
                closeCredits(other);
            }
        });
        var panel = root.querySelector('[data-vmedia-credits-panel]');
        var toggle = root.querySelector('[data-vmedia-credits-toggle]');
        if (panel) {
            panel.hidden = false;
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    function bindCredits(scope) {
        (scope || document).querySelectorAll('[data-vmedia-credits]').forEach(function (root) {
            if (root.__vmediaCreditsBound) {
                return;
            }
            root.__vmediaCreditsBound = true;
            var toggle = root.querySelector('[data-vmedia-credits-toggle]');
            if (! toggle) {
                return;
            }
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var panel = root.querySelector('[data-vmedia-credits-panel]');
                if (panel && panel.hidden) {
                    openCredits(root);
                } else {
                    closeCredits(root);
                }
            });
        });
    }

    if (! window.__vmediaCreditsDocBound) {
        window.__vmediaCreditsDocBound = true;
        document.addEventListener('click', function (event) {
            var target = event.target;
            if (! (target instanceof Element)) {
                return;
            }
            if (target.closest('[data-vmedia-credits]')) {
                return;
            }
            document.querySelectorAll('[data-vmedia-credits]').forEach(closeCredits);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-vmedia-credits]').forEach(closeCredits);
            }
        });
    }

    function bindRoot(root) {
        if (! root || root.__vmediaLightboxBound || root.tagName === 'SCRIPT') {
            return;
        }
        root.__vmediaLightboxBound = true;
        bindCredits(root);

        var dataEl = root.querySelector('[data-vmedia-gallery-data]');
        var dialog = root.querySelector('[data-vmedia-gallery-dialog]');
        if (! dataEl || ! dialog) {
            return;
        }

        if (dialog.parentElement !== document.body) {
            document.body.appendChild(dialog);
        }

        bindCredits(dialog);

        var image = dialog.querySelector('[data-vmedia-gallery-image]');
        var captionAbove = dialog.querySelector('[data-vmedia-gallery-caption-above]');
        var captionBelow = dialog.querySelector('[data-vmedia-gallery-caption-below]');
        var creditsRoot = dialog.querySelector('[data-vmedia-gallery-credits]');
        var creditsText = dialog.querySelector('[data-vmedia-gallery-credits-text]');
        var captionPosition = dialog.getAttribute('data-vmedia-caption-position') || 'below';
        var slides = [];
        try {
            slides = JSON.parse((dataEl.textContent || dataEl.innerText || '').trim() || '[]');
        } catch (err) {
            slides = [];
        }
        if (! Array.isArray(slides) || slides.length === 0 || ! image) {
            return;
        }

        var index = 0;

        function setCaption(el, text) {
            if (! el) {
                return;
            }
            el.textContent = text;
            el.hidden = text === '';
        }

        function render() {
            var slide = slides[index];
            if (! slide) {
                return;
            }
            image.src = slide.url || slide.thumb || '';
            image.alt = slide.alt || '';

            var captionText = slide.caption || '';
            if (captionPosition === 'above' || captionPosition === 'overlay') {
                setCaption(captionAbove, captionText);
                setCaption(captionBelow, '');
            } else {
                setCaption(captionAbove, '');
                setCaption(captionBelow, captionText);
            }

            var credits = String(slide.credits || '').trim();
            if (creditsRoot) {
                closeCredits(creditsRoot);
                if (credits !== '') {
                    creditsRoot.hidden = false;
                    if (creditsText) {
                        creditsText.textContent = credits;
                    }
                } else {
                    creditsRoot.hidden = true;
                }
            }
        }

        function open(nextIndex) {
            index = nextIndex;
            render();
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        }

        function closeDialog() {
            if (creditsRoot) {
                closeCredits(creditsRoot);
            }
            dialog.close();
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
        var stage = dialog.querySelector('.vmedia-gallery-dialog__stage');

        closeBtn && closeBtn.addEventListener('click', function () { closeDialog(); });
        prevBtn && prevBtn.addEventListener('click', function () {
            index = (index - 1 + slides.length) % slides.length;
            render();
        });
        nextBtn && nextBtn.addEventListener('click', function () {
            index = (index + 1) % slides.length;
            render();
        });

        dialog.addEventListener('click', function (event) {
            var target = event.target;
            if (target === dialog) {
                closeDialog();
                return;
            }
            if (target && target.hasAttribute && target.hasAttribute('data-vmedia-gallery-backdrop')) {
                closeDialog();
                return;
            }
            if (stage && target instanceof Node && ! stage.contains(target)
                && ! (prevBtn && prevBtn.contains(target))
                && ! (nextBtn && nextBtn.contains(target))
                && ! (closeBtn && closeBtn.contains(target))) {
                if (target.classList && target.classList.contains('vmedia-gallery-dialog__row')) {
                    closeDialog();
                }
            }
        });

        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDialog();
                return;
            }
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
        bindCredits(document);
        document.querySelectorAll('[data-vmedia-gallery-lightbox]').forEach(bindRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
