/**
 * Public lightbox + credits popovers for VoodMedia gallery blocks
 * and standalone VoodBuilder images (`data-vb-lightbox`).
 * Loaded on VoodBuilder layouts — not via @push from block HTML (Grapes/DOM hydrate drop scripts).
 */
(function () {
    'use strict';

    /**
     * Allow plain text + http(s) <a> only (Unsplash credit HTML).
     *
     * @param {string} raw
     * @returns {string}
     */
    function sanitizeCreditsHtml(raw) {
        var text = String(raw == null ? '' : raw).trim();
        if (text === '') {
            return '';
        }
        if (text.indexOf('<') === -1) {
            var esc = document.createElement('div');
            esc.textContent = text;
            return esc.innerHTML;
        }

        var template = document.createElement('template');
        template.innerHTML = text;
        var out = document.createElement('div');

        function walk(node, parent) {
            if (node.nodeType === Node.TEXT_NODE) {
                parent.appendChild(document.createTextNode(node.textContent || ''));
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }
            var el = /** @type {Element} */ (node);
            var tag = el.tagName.toLowerCase();
            if (tag === 'a') {
                var href = String(el.getAttribute('href') || '').trim();
                if (/^https?:\/\//i.test(href)) {
                    var a = document.createElement('a');
                    a.href = href;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    Array.prototype.forEach.call(el.childNodes, function (child) {
                        walk(child, a);
                    });
                    parent.appendChild(a);
                    return;
                }
            }
            Array.prototype.forEach.call(el.childNodes, function (child) {
                walk(child, parent);
            });
        }

        Array.prototype.forEach.call(template.content.childNodes, function (child) {
            walk(child, out);
        });

        return out.innerHTML;
    }

    /**
     * @param {Element|null} el
     * @param {string} raw
     */
    function setCreditsContent(el, raw) {
        if (! el) {
            return;
        }
        var html = sanitizeCreditsHtml(raw);
        if (html.indexOf('<') !== -1) {
            el.innerHTML = html;
        } else {
            el.textContent = String(raw == null ? '' : raw).trim();
        }
    }

    function creditsHost(root) {
        return root
            ? (root.closest('.vmedia-gallery-item')
                || root.closest('.vb-image')
                || root.closest('[data-vb-image]'))
            : null;
    }

    function closeCredits(root) {
        if (! root) {
            return;
        }
        var panel = root.querySelector('[data-vmedia-credits-panel]');
        var toggle = root.querySelector('[data-vmedia-credits-toggle]');
        root.classList.remove('is-open');
        var item = creditsHost(root);
        if (item) {
            item.classList.remove('is-credits-open');
        }
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
        root.classList.add('is-open');
        var item = creditsHost(root);
        if (item) {
            item.classList.add('is-credits-open');
        }
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
        var loadToken = 0;

        function setCaption(el, text) {
            if (! el) {
                return;
            }
            el.textContent = text;
            el.hidden = text === '';
        }

        function revealImage() {
            image.style.opacity = '1';
        }

        function hideImage() {
            image.style.opacity = '0';
        }

        function clearImage() {
            loadToken += 1;
            hideImage();
            image.removeAttribute('src');
            image.alt = '';
        }

        function applyMeta(slide) {
            var captionText = slide.caption || '';
            // Lightbox always shows caption under the image; grid "above/overlay"
            // only applies to the page layout, not the dialog.
            setCaption(captionAbove, '');
            setCaption(captionBelow, captionText);

            var credits = String(slide.credits || '').trim();
            if (creditsRoot) {
                closeCredits(creditsRoot);
                if (credits !== '') {
                    creditsRoot.hidden = false;
                    setCreditsContent(creditsText, credits);
                } else {
                    creditsRoot.hidden = true;
                    setCreditsContent(creditsText, '');
                }
            }
        }

        function render() {
            var slide = slides[index];
            if (! slide) {
                return;
            }

            var nextSrc = slide.url || slide.thumb || '';
            var token = ++loadToken;

            applyMeta(slide);
            image.alt = slide.alt || '';

            // Hide until the new frame is ready so the previous photo never flashes.
            hideImage();

            if (! nextSrc) {
                image.removeAttribute('src');
                return;
            }

            var showWhenReady = function () {
                if (token !== loadToken) {
                    return;
                }
                revealImage();
            };

            if (image.getAttribute('src') === nextSrc && image.complete) {
                showWhenReady();
                return;
            }

            image.onload = function () {
                image.onload = null;
                image.onerror = null;
                showWhenReady();
            };
            image.onerror = function () {
                image.onload = null;
                image.onerror = null;
                showWhenReady();
            };
            image.src = nextSrc;

            // Cached images may already be complete synchronously after setting src.
            if (image.complete) {
                image.onload = null;
                image.onerror = null;
                showWhenReady();
            }
        }

        function open(nextIndex) {
            index = nextIndex;
            render();
            if (typeof dialog.showModal === 'function' && ! dialog.open) {
                dialog.showModal();
            }
        }

        function closeDialog() {
            if (creditsRoot) {
                closeCredits(creditsRoot);
            }
            dialog.close();
            clearImage();
            setCaption(captionAbove, '');
            setCaption(captionBelow, '');
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

    /**
     * Shared dialog for standalone page images (`figure[data-vb-lightbox="1"]`).
     *
     * @param {Document} doc
     * @returns {HTMLDialogElement|null}
     */
    function ensureStandaloneDialog(doc) {
        var existing = doc.querySelector('[data-vb-image-lightbox-dialog]');
        if (existing) {
            return /** @type {HTMLDialogElement} */ (existing);
        }

        var dialog = doc.createElement('dialog');
        dialog.className = 'vmedia-gallery-dialog';
        dialog.setAttribute('data-vb-image-lightbox-dialog', '');
        dialog.setAttribute('data-voodbuilder-skip-cta', 'true');
        dialog.setAttribute('aria-label', 'Image');
        dialog.innerHTML = ''
            + '<div class="vmedia-gallery-dialog__viewport" data-vb-image-lightbox-backdrop>'
            +   '<button type="button" class="vmedia-gallery-dialog__close" data-vb-image-lightbox-close data-voodbuilder-skip-cta="true" aria-label="Close">&times;</button>'
            +   '<div class="vmedia-gallery-dialog__row">'
            +     '<div class="vmedia-gallery-dialog__stage" style="grid-column: 1 / -1; max-width: min(96vw, 72rem); margin-inline: auto;">'
            +       '<div class="vmedia-gallery-dialog__media">'
            +         '<img src="" alt="" class="vmedia-gallery-dialog__image" data-vb-image-lightbox-image style="opacity:1">'
            +         '<div class="vmedia-gallery-credits vmedia-gallery-credits--on-dark vmedia-gallery-dialog__credits" data-vmedia-credits data-vb-image-lightbox-credits hidden>'
            +           '<button type="button" class="vmedia-gallery-credits__btn vmedia-gallery-credits__btn--on-dark" data-vmedia-credits-toggle data-voodbuilder-skip-cta="true" aria-expanded="false" aria-label="Credits" title="Credits"><span aria-hidden="true">i</span></button>'
            +           '<div class="vmedia-gallery-credits__panel" data-vmedia-credits-panel data-vb-image-lightbox-credits-text hidden role="note"></div>'
            +         '</div>'
            +       '</div>'
            +       '<p class="vmedia-gallery-dialog__caption" data-vb-image-lightbox-caption hidden></p>'
            +     '</div>'
            +   '</div>'
            + '</div>';

        doc.body.appendChild(dialog);

        var closeBtn = dialog.querySelector('[data-vb-image-lightbox-close]');
        var imageEl = dialog.querySelector('[data-vb-image-lightbox-image]');
        var creditsRoot = dialog.querySelector('[data-vb-image-lightbox-credits]');

        function closeDialog() {
            if (creditsRoot) {
                closeCredits(creditsRoot);
            }
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            }
            if (imageEl) {
                imageEl.removeAttribute('src');
                imageEl.alt = '';
            }
        }

        closeBtn && closeBtn.addEventListener('click', closeDialog);
        dialog.addEventListener('click', function (event) {
            var target = event.target;
            if (target === dialog || (target && target.hasAttribute && target.hasAttribute('data-vb-image-lightbox-backdrop'))) {
                closeDialog();
            }
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDialog();
            }
        });

        bindCredits(dialog);

        return dialog;
    }

    /**
     * @param {HTMLElement} figure
     * @param {HTMLImageElement} img
     */
    function openStandaloneLightbox(figure, img) {
        var doc = figure.ownerDocument || document;
        var dialog = ensureStandaloneDialog(doc);
        if (! dialog) {
            return;
        }

        var imageEl = dialog.querySelector('[data-vb-image-lightbox-image]');
        var captionEl = dialog.querySelector('[data-vb-image-lightbox-caption]');
        var creditsRoot = dialog.querySelector('[data-vb-image-lightbox-credits]');
        var creditsText = dialog.querySelector('[data-vb-image-lightbox-credits-text]');

        if (! imageEl) {
            return;
        }

        imageEl.src = img.currentSrc || img.src || '';
        imageEl.alt = img.alt || '';

        var caption = '';
        var figcaption = figure.querySelector('figcaption');
        if (figcaption) {
            caption = String(figcaption.textContent || '').trim();
        }
        if (caption === '') {
            caption = String(img.getAttribute('data-vb-caption') || img.getAttribute('data-vb-media-caption') || '').trim();
        }
        if (captionEl) {
            captionEl.textContent = caption;
            captionEl.hidden = caption === '';
        }

        var credits = String(
            img.getAttribute('data-vb-credits')
            || img.getAttribute('data-vb-media-credits')
            || '',
        ).trim();
        if (creditsRoot) {
            closeCredits(creditsRoot);
            if (credits !== '') {
                creditsRoot.hidden = false;
                setCreditsContent(creditsText, credits);
            } else {
                creditsRoot.hidden = true;
                setCreditsContent(creditsText, '');
            }
        }

        if (typeof dialog.showModal === 'function' && ! dialog.open) {
            dialog.showModal();
        }
    }

    /**
     * Click-to-zoom for standalone images. Skipped inside the Grapes canvas iframe.
     *
     * @param {ParentNode|Document|null} scope
     */
    function bindStandaloneLightbox(scope) {
        var root = scope || document;
        var doc = root.nodeType === 9 ? root : (root.ownerDocument || document);

        if (doc.documentElement && doc.documentElement.classList.contains('voodbuilder-canvas-ready')) {
            return;
        }

        doc.querySelectorAll('figure.vb-image[data-vb-lightbox="1"], figure[data-vb-image][data-vb-lightbox="1"]').forEach(function (figure) {
            if (figure.__vbLightboxBound) {
                return;
            }
            var img = figure.querySelector('img');
            if (! img) {
                return;
            }
            figure.__vbLightboxBound = true;
            img.style.cursor = 'zoom-in';
            if (! img.hasAttribute('tabindex')) {
                img.setAttribute('tabindex', '0');
            }
            img.setAttribute('role', 'button');
            img.setAttribute('aria-label', img.alt ? (img.alt + ' (enlarge)') : 'Enlarge image');

            var open = function (event) {
                if (event.target && event.target.closest && event.target.closest('[data-vmedia-credits]')) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                openStandaloneLightbox(figure, img);
            };

            img.addEventListener('click', open);
            img.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    open(event);
                }
            });
        });
    }

    function boot() {
        bindCredits(document);
        bindStandaloneLightbox(document);
        document.querySelectorAll('[data-vmedia-gallery-lightbox]').forEach(bindRoot);
    }

    window.__vmediaBindCredits = bindCredits;
    window.__vmediaBindStandaloneLightbox = bindStandaloneLightbox;
    window.__vmediaSanitizeCreditsHtml = sanitizeCreditsHtml;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
