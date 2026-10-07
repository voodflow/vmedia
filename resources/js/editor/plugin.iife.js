/**
 * VoodMedia gallery block settings for the VoodBuilder right sidebar.
 * Soft-loaded on editor pages (no Vite rebuild of voodbuilder required).
 */
(function (window) {
    'use strict';

    var BLOCK_IDS = {
        vmedia_gallery_grid: true,
        vmedia_gallery_masonry: true,
        vmedia_gallery_featured: true,
        vmedia_gallery_marquee: true,
    };

    var refreshTimers = typeof WeakMap !== 'undefined' ? new WeakMap() : null;

    function label(editor, key, fallback) {
        return (editor && editor.__voodbuilderLabels && editor.__voodbuilderLabels[key]) || fallback;
    }

    /**
     * Match voodbuilder/resources/js/editor/voodbuilder-dynamic-config.js —
     * Grapes getHtml() breaks raw JSON quotes inside double-quoted attributes,
     * which blanks gallery settings on save/reload.
     */
    function parseBlockConfig(raw) {
        if (! raw || typeof raw !== 'string' || raw === '{}') {
            return {};
        }

        var textarea = document.createElement('textarea');
        textarea.innerHTML = raw;
        var entityDecoded = textarea.value || raw;
        var candidates = [entityDecoded, raw];

        for (var i = 0; i < candidates.length; i += 1) {
            try {
                var parsed = JSON.parse(candidates[i]);
                if (parsed && typeof parsed === 'object' && ! Array.isArray(parsed)) {
                    return parsed;
                }
            } catch (err) {
                // try next
            }
        }

        return {};
    }

    function encodeBlockConfig(config) {
        var json = '{}';
        try {
            json = JSON.stringify(config || {});
        } catch (err) {
            json = '{}';
        }

        return json
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function findBlockRoot(component) {
        var current = component;
        while (current) {
            var attrs = current.getAttributes ? current.getAttributes() : {};
            if (attrs['data-voodbuilder-block']) {
                return current;
            }
            current = current.parent ? current.parent() : null;
        }
        return null;
    }

    function readBlockId(root) {
        if (! root) {
            return '';
        }
        var attrs = root.getAttributes ? root.getAttributes() : {};
        var fromAttrs = attrs['data-voodbuilder-block']
            || attrs['data-voodbuilder-section-block']
            || '';
        if (String(fromAttrs).trim() !== '') {
            return String(fromAttrs).trim();
        }
        return '';
    }

    function readConfig(root) {
        var attrs = root.getAttributes ? root.getAttributes() : {};
        var fromModel = root.get ? root.get('voodbuilderConfig') : null;
        var raw = fromModel && typeof fromModel === 'object'
            ? Object.assign({}, fromModel)
            : parseBlockConfig(attrs['data-voodbuilder-config'] || '{}');
        return sanitizeConfig(raw);
    }

    /**
     * Drop stale caption_bg (often default black) so server normalize + editor
     * bake always follow caption_bg_color / opacity.
     */
    function sanitizeConfig(config) {
        var next = Object.assign({}, config || {});
        if (Object.prototype.hasOwnProperty.call(next, 'caption_bg')) {
            delete next.caption_bg;
        }
        return next;
    }

    function persistConfig(editor, root, config) {
        var clean = sanitizeConfig(config);
        if (root.set) {
            root.set('voodbuilderConfig', clean, { silent: true });
        }
        if (root.addAttributes) {
            root.addAttributes({
                'data-voodbuilder-config': encodeBlockConfig(clean),
            });
        }
        if (editor && editor.__voodbuilderTrackSaveDirty !== false) {
            editor.__voodbuilderPageSaveClean = false;
        }
        return clean;
    }

    function bumpPageCss(editor) {
        try {
            if (typeof editor.__voodbuilderForcePageCssRebuild === 'function') {
                editor.__voodbuilderForcePageCssRebuild(120);
            }
        } catch (err) {
            // ignore
        }
        try {
            editor.trigger('voodbuilder:page-css-invalidate');
        } catch (err) {
            // ignore
        }
    }

    var EDITOR_COLUMN_OVERRIDE_CSS = [
        '/* Editor: honor author column count even when the iframe is phone-narrow. */',
        '.vmedia-gallery-block .vmedia-gallery-cols[data-vmedia-columns]{',
        'grid-template-columns:repeat(var(--vmedia-columns, 3),minmax(0,1fr))!important;',
        '}',
        '.vmedia-gallery-block .vmedia-gallery-masonry-cols[data-vmedia-columns]{',
        'column-count:var(--vmedia-columns, 3)!important;',
        '}',
    ].join('');

    function injectGalleryCanvasCss(editor) {
        var link = document.querySelector('link[data-vmedia-gallery-css]');
        var inline = document.querySelector('style[data-vmedia-gallery-css]');
        var href = link ? link.href : null;

        function into(doc) {
            if (! doc || ! doc.head) {
                return;
            }

            if (! doc.getElementById('vmedia-gallery-canvas-css')) {
                if (inline && inline.textContent) {
                    var style = doc.createElement('style');
                    style.id = 'vmedia-gallery-canvas-css';
                    style.setAttribute('data-vmedia-gallery-css', '1');
                    style.textContent = inline.textContent;
                    doc.head.appendChild(style);
                } else if (href) {
                    var cloned = doc.createElement('link');
                    cloned.id = 'vmedia-gallery-canvas-css';
                    cloned.rel = 'stylesheet';
                    cloned.href = href;
                    cloned.setAttribute('data-vmedia-gallery-css', '1');
                    doc.head.appendChild(cloned);
                }
            }

            if (! doc.getElementById('vmedia-gallery-editor-columns')) {
                var override = doc.createElement('style');
                override.id = 'vmedia-gallery-editor-columns';
                override.textContent = EDITOR_COLUMN_OVERRIDE_CSS;
                doc.head.appendChild(override);
            }
        }

        into(document);
        var frameDoc = editor && editor.Canvas && typeof editor.Canvas.getDocument === 'function'
            ? editor.Canvas.getDocument()
            : null;
        into(frameDoc);

        if (editor && typeof editor.on === 'function' && ! editor.__vmediaGalleryCssFrameBound) {
            editor.__vmediaGalleryCssFrameBound = true;
            editor.on('canvas:frame:load', function () {
                into(editor.Canvas.getDocument());
                rebakeAllGalleryCaptions(editor);
            });
        }
    }

    function scheduleFullRefresh(editor, root, options) {
        var opts = options && typeof options === 'object' ? options : {};
        var rebuildCss = opts.rebuildCss === true;
        var runAfter = typeof opts.afterRefresh === 'function' ? opts.afterRefresh : null;

        function finish() {
            if (rebuildCss) {
                bumpPageCss(editor);
                // Compile finishes later — rebake again when CSS is idle / compiled.
                scheduleRebakeWhenCssIdle(editor, root);
            } else {
                rebakeCaptionBgAfterRefresh(editor, root, {
                    vmedia: editor && editor.__voodbuilderVmedia,
                });
            }
            if (runAfter) {
                try {
                    runAfter();
                } catch (err2) {
                    // ignore
                }
            }
        }

        if (! refreshTimers) {
            editor.trigger('voodbuilder:refresh-dynamic-block', root);
            window.setTimeout(finish, 280);
            return;
        }
        var existing = refreshTimers.get(root);
        if (existing) {
            window.clearTimeout(existing);
        }
        refreshTimers.set(root, window.setTimeout(function () {
            refreshTimers.delete(root);
            delete root.__voodbuilderLastDynamicRenderFingerprint;
            delete root.__voodbuilderLastDynamicRenderHtml;
            editor.trigger('voodbuilder:refresh-dynamic-block', root);
            window.setTimeout(finish, 280);
        }, 160));
    }

    function scheduleRebakeWhenCssIdle(editor, root) {
        var context = { vmedia: editor && editor.__voodbuilderVmedia };
        var paint = function () {
            rebakeCaptionBgAfterRefresh(editor, root, context);
        };

        paint();

        if (typeof editor.__voodbuilderWaitForPageCssIdle === 'function') {
            editor.__voodbuilderWaitForPageCssIdle(8000).then(paint).catch(function () {
                paint();
            });
            return;
        }

        window.setTimeout(paint, 600);
        window.setTimeout(paint, 1400);
    }

    function writeConfig(editor, root, config, options) {
        persistConfig(editor, root, config);
        scheduleFullRefresh(editor, root, options);
    }

    /** Layout / structure changes — remount block (optional page CSS rebuild). */
    function patchConfigs(editor, root, patch, options) {
        var next = sanitizeConfig(Object.assign({}, readConfig(root), patch));
        writeConfig(editor, root, next, options);
    }

    /**
     * Caption colour / opacity: persist + bake rgba into Grapes attrs.
     * No dynamic remount and no "Compiling styles…" (that wiped the red paint).
     */
    function patchCaptionStyle(editor, root, context, patch) {
        var next = sanitizeConfig(Object.assign({}, readConfig(root), patch));
        persistConfig(editor, root, next);
        paintCaptionBgFromConfig(editor, root, context, next);
    }

    function galleryDomRoot(root) {
        if (! root) {
            return null;
        }
        if (typeof root.getEl === 'function') {
            var el = root.getEl();
            if (el) {
                return el;
            }
        }
        var view = typeof root.getView === 'function' ? root.getView() : null;
        return view && view.el ? view.el : null;
    }

    function canvasDocument(editor) {
        try {
            if (editor && editor.Canvas && typeof editor.Canvas.getDocument === 'function') {
                return editor.Canvas.getDocument() || document;
            }
        } catch (err) {
            // ignore
        }
        return document;
    }

    function rgbChannelsFromCssColor(editor, cssColor) {
        var value = String(cssColor || '').trim();
        if (value === '') {
            return null;
        }

        var hexMatch = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (hexMatch) {
            var raw = hexMatch[1];
            if (raw.length === 3) {
                raw = raw[0] + raw[0] + raw[1] + raw[1] + raw[2] + raw[2];
            }
            return {
                r: parseInt(raw.slice(0, 2), 16) || 0,
                g: parseInt(raw.slice(2, 4), 16) || 0,
                b: parseInt(raw.slice(4, 6), 16) || 0,
            };
        }

        var doc = canvasDocument(editor);
        var probe = doc.createElement('div');
        probe.style.cssText = 'position:absolute;left:-99999px;top:0;color:' + value;
        (doc.body || doc.documentElement).appendChild(probe);
        var computed = '';
        try {
            computed = (doc.defaultView || window).getComputedStyle(probe).color || '';
        } catch (err) {
            computed = '';
        }
        probe.remove();

        var rgb = computed.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
        if (! rgb) {
            return null;
        }

        return {
            r: Math.round(Number(rgb[1])) || 0,
            g: Math.round(Number(rgb[2])) || 0,
            b: Math.round(Number(rgb[3])) || 0,
        };
    }

    function channelsToHex(channels) {
        if (! channels) {
            return null;
        }
        function part(n) {
            var h = Math.max(0, Math.min(255, n)).toString(16);
            return h.length === 1 ? '0' + h : h;
        }
        return '#' + part(channels.r) + part(channels.g) + part(channels.b);
    }

    /**
     * Resolve theme token (vp-brand-1, …) to a concrete hex from the canvas theme,
     * not the editor chrome defaults (those look blue/indigo).
     */
    function resolveThemeTokenHex(editor, token) {
        var name = String(token || '').trim();
        if (name.indexOf('vp-') !== 0) {
            return null;
        }

        var doc = canvasDocument(editor);
        var roots = [doc.documentElement, doc.body, document.documentElement];
        for (var i = 0; i < roots.length; i += 1) {
            if (! roots[i]) {
                continue;
            }
            var raw = '';
            try {
                raw = (doc.defaultView || window).getComputedStyle(roots[i])
                    .getPropertyValue('--color-' + name)
                    .trim();
            } catch (err) {
                raw = '';
            }
            if (raw) {
                var fromVar = channelsToHex(rgbChannelsFromCssColor(editor, raw));
                if (fromVar) {
                    return fromVar;
                }
            }
        }

        var paletteCss = String(editor && editor.__voodbuilderThemePaletteCss || '').trim();
        if (paletteCss !== '') {
            var re = new RegExp('--color-' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*:\\s*([^;}{]+)');
            var match = paletteCss.match(re);
            if (match && match[1]) {
                var fromCss = channelsToHex(rgbChannelsFromCssColor(editor, match[1].trim()));
                if (fromCss) {
                    return fromCss;
                }
            }
        }

        return channelsToHex(rgbChannelsFromCssColor(editor, 'var(--color-' + name + ')'));
    }

    function hexToCaptionCss(hex, opacityPercent) {
        var raw = String(hex || '#000000').replace('#', '');
        if (raw.length === 3) {
            raw = raw[0] + raw[0] + raw[1] + raw[1] + raw[2] + raw[2];
        }
        var r = parseInt(raw.slice(0, 2), 16) || 0;
        var g = parseInt(raw.slice(2, 4), 16) || 0;
        var b = parseInt(raw.slice(4, 6), 16) || 0;
        var pct = Math.max(0, Math.min(100, Number(opacityPercent) || 0));
        var alpha = Math.round((pct / 100) * 100) / 100;
        if (alpha >= 1) {
            return 'rgb(' + r + ', ' + g + ', ' + b + ')';
        }
        if (alpha <= 0) {
            return 'transparent';
        }
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function resolveCaptionBgCss(editor, context, colorToken, opacityPercent) {
        var token = String(colorToken || 'black').trim();
        if (token.indexOf('vp-') === 0) {
            var themeHex = resolveThemeTokenHex(editor, token);
            if (themeHex) {
                // Bake concrete rgba so canvas remounts don't flash black when
                // color-mix(var(--color-vp-*)) fails to resolve momentarily.
                return hexToCaptionCss(themeHex, opacityPercent);
            }
            var pct = Math.max(0, Math.min(100, Number(opacityPercent) || 0));
            var cssVar = 'var(--color-' + token + ')';
            if (pct >= 100) {
                return cssVar;
            }
            if (pct <= 0) {
                return 'transparent';
            }
            return 'color-mix(in srgb, ' + cssVar + ' ' + pct + '%, transparent)';
        }
        var options = captionColorOptions(editor, context);
        var hex = '#000000';
        for (var i = 0; i < options.length; i += 1) {
            if (String(options[i].value) === token && options[i].hex) {
                hex = String(options[i].hex);
                break;
            }
        }
        if (token === 'white') {
            hex = '#ffffff';
        }
        return hexToCaptionCss(hex, opacityPercent);
    }

    function mergeCaptionBgStyle(styleText, cssValue) {
        var next = String(styleText || '')
            .replace(/(?:^|;)\s*--vmedia-caption-bg\s*:[^;]*/gi, '')
            .replace(/;;+/g, ';')
            .replace(/^;|;$/g, '')
            .trim();
        var baked = '--vmedia-caption-bg: ' + cssValue;
        return next === '' ? baked : (next + '; ' + baked);
    }

    function bakeCaptionBgOnComponents(root, cssValue) {
        if (! root || ! cssValue) {
            return;
        }

        function walk(component) {
            if (! component) {
                return;
            }
            var tag = String(component.get ? (component.get('tagName') || '') : '').toLowerCase();
            var attrs = component.getAttributes ? component.getAttributes() : {};
            var className = String(attrs.class || '');
            var isCaption = tag === 'figcaption'
                || className.indexOf('vmedia-gallery-item__caption') !== -1
                || className.indexOf('vmedia-gallery-dialog') !== -1
                || Object.prototype.hasOwnProperty.call(attrs, 'data-vmedia-gallery-dialog');

            if (isCaption || (attrs.style && String(attrs.style).indexOf('--vmedia-caption-bg') !== -1)) {
                component.addAttributes({
                    style: mergeCaptionBgStyle(attrs.style, cssValue),
                });
            }

            var children = component.components ? component.components() : null;
            if (children && typeof children.forEach === 'function') {
                children.forEach(walk);
            } else if (children && typeof children.length === 'number') {
                for (var i = 0; i < children.length; i += 1) {
                    walk(children.at ? children.at(i) : children[i]);
                }
            }
        }

        walk(root);
    }

    function livePaintCaptionBg(root, cssValue) {
        var el = galleryDomRoot(root);
        if (! el || ! cssValue) {
            return;
        }
        el.style.setProperty('--vmedia-caption-bg', cssValue);
        var nodes = el.querySelectorAll
            ? el.querySelectorAll(
                '[style*="--vmedia-caption-bg"], .vmedia-gallery-item__caption, .vmedia-gallery-dialog, .vmedia-gallery-dialog__caption',
            )
            : [];
        for (var i = 0; i < nodes.length; i += 1) {
            nodes[i].style.setProperty('--vmedia-caption-bg', cssValue);
        }
    }

    function paintCaptionBgFromConfig(editor, root, context, config) {
        var color = String((config && config.caption_bg_color) || 'black');
        var opacity = Number((config && config.caption_bg_opacity) ?? 82);
        var cssValue = resolveCaptionBgCss(editor, context, color, opacity);
        livePaintCaptionBg(root, cssValue);
        bakeCaptionBgOnComponents(root, cssValue);
        return cssValue;
    }

    function rebakeCaptionBgAfterRefresh(editor, root, context) {
        paintCaptionBgFromConfig(editor, root, context, readConfig(root));
    }

    function eachGalleryRoot(editor, callback) {
        var wrappers = editor && editor.getWrapper ? editor.getWrapper() : null;
        if (! wrappers || typeof wrappers.find !== 'function') {
            return;
        }
        var found = wrappers.find('[data-voodbuilder-block^="vmedia_gallery_"]') || [];
        var list = typeof found.toArray === 'function' ? found.toArray() : found;
        for (var i = 0; i < list.length; i += 1) {
            try {
                callback(list[i]);
            } catch (err) {
                // ignore per-block failures
            }
        }
    }

    function rebakeAllGalleryCaptions(editor) {
        var context = { vmedia: editor && editor.__voodbuilderVmedia };
        eachGalleryRoot(editor, function (root) {
            rebakeCaptionBgAfterRefresh(editor, root, context);
        });
    }

    function bindGalleryCaptionLifecycle(editor) {
        if (! editor || typeof editor.on !== 'function' || editor.__vmediaGalleryCaptionLifecycleBound) {
            return;
        }
        editor.__vmediaGalleryCaptionLifecycleBound = true;

        editor.on('voodbuilder:dynamic-blocks-refreshed', function () {
            window.setTimeout(function () {
                rebakeAllGalleryCaptions(editor);
            }, 50);
        });

        editor.on('voodbuilder:page-css-compiled', function () {
            window.setTimeout(function () {
                rebakeAllGalleryCaptions(editor);
            }, 30);
        });

        // First paint after boot (config already has vp-brand-1 but HTML still color-mix/black).
        window.setTimeout(function () {
            rebakeAllGalleryCaptions(editor);
        }, 400);
        window.setTimeout(function () {
            rebakeAllGalleryCaptions(editor);
        }, 1200);
    }

    function galleryOptions(editor, context) {
        var bridge = (context && context.vmedia)
            || (editor && editor.__voodbuilderVmedia)
            || (typeof window !== 'undefined' ? window.__voodbuilderVmedia : null)
            || {};
        var galleries = Array.isArray(bridge.galleries) ? bridge.galleries : [];
        var options = [{ value: '', label: label(editor, 'vmediaNoGallery', 'Select a gallery…') }];
        galleries.forEach(function (item) {
            options.push({
                value: String(item.value),
                label: String(item.label || item.value),
            });
        });
        return options;
    }

    function createFormSection(title) {
        var section = document.createElement('div');
        section.className = 'voodbuilder-editor-form-section';

        var heading = document.createElement('div');
        heading.className = 'voodbuilder-editor-form-section-title';
        heading.textContent = title;

        var fields = document.createElement('div');
        fields.className = 'voodbuilder-editor-form-fields';

        section.append(heading, fields);
        return { section: section, fields: fields };
    }

    function createSelectField(options) {
        var field = document.createElement('div');
        field.className = 'voodbuilder-editor-form-field';

        var labelEl = document.createElement('label');
        labelEl.className = 'voodbuilder-editor-form-label';
        labelEl.textContent = options.label;

        var select = document.createElement('select');
        select.className = 'voodbuilder-editor-input';
        select.name = options.name;
        if (options.searchable) {
            select.setAttribute('data-vb-search', '1');
        }
        (options.options || []).forEach(function (opt) {
            var option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            if (opt.hex) {
                option.setAttribute('data-hex', String(opt.hex));
            } else if (opt.css) {
                option.setAttribute('data-hex', String(opt.css));
            }
            if (String(opt.value) === String(options.value ?? '')) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        select.addEventListener('change', function () {
            options.onChange(select.value);
        });

        field.append(labelEl, select);
        return field;
    }

    /**
     * Same range chrome as Decorations → Gradient stop sliders
     * (`.voodbuilder-editor-deco-stop__range`), not a generic form input.
     */
    function createRangeField(options) {
        var field = document.createElement('div');
        field.className = 'voodbuilder-editor-form-field';

        var labelEl = document.createElement('label');
        labelEl.className = 'voodbuilder-editor-form-label';
        labelEl.textContent = options.label;

        var row = document.createElement('div');
        row.className = 'voodbuilder-editor-deco-stop__pos';

        var posLabel = document.createElement('span');
        posLabel.className = 'voodbuilder-editor-deco-stop__pos-label';
        posLabel.textContent = options.posLabel || '%';

        var input = document.createElement('input');
        input.type = 'range';
        input.className = 'voodbuilder-editor-deco-stop__range';
        input.name = options.name;
        input.min = String(options.min != null ? options.min : 0);
        input.max = String(options.max != null ? options.max : 100);
        input.step = String(options.step != null ? options.step : 1);
        input.value = String(options.value ?? 100);
        input.setAttribute('aria-label', options.label);

        var readout = document.createElement('span');
        readout.className = 'voodbuilder-editor-deco-stop__pos-value';
        readout.textContent = String(options.value ?? 100) + '%';

        input.addEventListener('input', function () {
            var value = Number(input.value) || 0;
            readout.textContent = String(value) + '%';
            if (typeof options.onInput === 'function') {
                options.onInput(value);
            }
        });
        input.addEventListener('change', function () {
            var value = Number(input.value) || 0;
            readout.textContent = String(value) + '%';
            if (typeof options.onChange === 'function') {
                options.onChange(value);
            }
        });

        row.append(posLabel, input, readout);
        field.append(labelEl, row);
        return field;
    }

    function captionColorOptions(editor, context) {
        var bridge = (context && context.vmedia)
            || (editor && editor.__voodbuilderVmedia)
            || (typeof window !== 'undefined' ? window.__voodbuilderVmedia : null)
            || {};
        var colors = Array.isArray(bridge.captionColors) ? bridge.captionColors : [];
        if (colors.length > 0) {
            return colors.map(function (item) {
                var value = String(item.value);
                var hex = item.hex ? String(item.hex) : undefined;
                if (! hex && value.indexOf('vp-') === 0) {
                    hex = resolveThemeTokenHex(editor, value) || undefined;
                }
                return {
                    value: value,
                    label: String(item.label || item.value),
                    hex: hex,
                    css: item.css ? String(item.css) : undefined,
                };
            });
        }
        return [
            { value: 'black', label: 'black', hex: '#000000' },
            { value: 'white', label: 'white', hex: '#ffffff' },
        ];
    }

    function createTextInputField(options) {
        var field = document.createElement('div');
        field.className = 'voodbuilder-editor-form-field';

        var labelEl = document.createElement('label');
        labelEl.className = 'voodbuilder-editor-form-label';
        labelEl.textContent = options.label;

        var input = document.createElement('input');
        input.type = options.type || 'text';
        input.className = 'voodbuilder-editor-input';
        input.name = options.name;
        input.value = options.value || '';
        if (options.min != null) {
            input.min = String(options.min);
        }
        if (options.max != null) {
            input.max = String(options.max);
        }
        input.addEventListener('change', function () {
            options.onChange(input.value);
        });

        field.append(labelEl, input);
        return field;
    }

    function createToggleField(options) {
        var field = document.createElement('div');
        field.className = 'voodbuilder-editor-form-field';
        field.style.display = 'flex';
        field.style.alignItems = 'center';
        field.style.gap = '0.5rem';

        var input = document.createElement('input');
        input.type = 'checkbox';
        input.name = options.name;
        input.checked = !! options.value;
        input.addEventListener('change', function () {
            options.onChange(!! input.checked);
        });

        var labelEl = document.createElement('label');
        labelEl.className = 'voodbuilder-editor-form-label';
        labelEl.style.margin = '0';
        labelEl.textContent = options.label;

        field.append(input, labelEl);
        return field;
    }

    function isGridLike(blockId) {
        return blockId === 'vmedia_gallery_grid'
            || blockId === 'vmedia_gallery_masonry'
            || blockId === 'vmedia_gallery_featured';
    }

    function isMarquee(blockId) {
        return blockId === 'vmedia_gallery_marquee';
    }

    function mount(editor, context) {
        if (editor.__vmediaBlockSettingsRegistered) {
            return;
        }

        editor.__voodbuilderVmedia = (context && context.vmedia)
            || editor.__voodbuilderVmedia
            || (typeof window !== 'undefined' ? window.__voodbuilderVmedia : null)
            || null;

        var api = window.VoodbuilderEditor;
        var registerBlockSettings = api && typeof api.registerBlockSettings === 'function'
            ? api.registerBlockSettings
            : null;

        if (typeof registerBlockSettings !== 'function') {
            return;
        }

        editor.__vmediaBlockSettingsRegistered = true;
        injectGalleryCanvasCss(editor);
        bindGalleryCaptionLifecycle(editor);

        if (typeof editor.on === 'function' && ! editor.__vmediaGalleryInsertCssBound) {
            editor.__vmediaGalleryInsertCssBound = true;
            editor.on('component:add', function (component) {
                var root = findBlockRoot(component) || component;
                var blockId = readBlockId(root);
                if (! blockId || blockId.indexOf('vmedia_gallery_') !== 0) {
                    return;
                }
                injectGalleryCanvasCss(editor);
                bumpPageCss(editor);
                window.setTimeout(function () { bumpPageCss(editor); }, 300);
                window.setTimeout(function () {
                    bumpPageCss(editor);
                    rebakeAllGalleryCaptions(editor);
                }, 900);
            });
        }

        registerBlockSettings({
            id: 'vmedia_gallery_blocks',
            blockIds: Object.keys(BLOCK_IDS),
            matchBlockId: function (blockId) {
                return !! BLOCK_IDS[blockId]
                    || (typeof blockId === 'string' && blockId.indexOf('vmedia_gallery_') === 0);
            },
            findRoot: function (component) {
                return findBlockRoot(component);
            },
            render: function (args) {
                var settingsMount = args.mount;
                var root = args.root;
                var gjsEditor = args.editor;
                var config = readConfig(root);
                var blockId = readBlockId(root);
                var form = createFormSection(label(gjsEditor, 'vmediaSettingsTitle', 'Gallery'));

                form.fields.appendChild(createSelectField({
                    label: label(gjsEditor, 'vmediaGallery', 'Gallery'),
                    name: 'gallery_id',
                    value: String(config.gallery_id || ''),
                    options: galleryOptions(gjsEditor, context),
                    onChange: function (value) {
                        patchConfigs(gjsEditor, root, {
                            gallery_id: value === '' ? null : Number(value),
                        }, { rebuildCss: true });
                    },
                }));

                form.fields.appendChild(createTextInputField({
                    label: label(gjsEditor, 'vmediaHeading', 'Heading'),
                    name: 'heading',
                    value: String(config.heading || ''),
                    onChange: function (value) {
                        patchConfigs(gjsEditor, root, { heading: value });
                    },
                }));

                form.fields.appendChild(createTextInputField({
                    label: label(gjsEditor, 'vmediaLimit', 'Max images'),
                    name: 'limit',
                    type: 'number',
                    min: 0,
                    max: 48,
                    value: String(config.limit ?? 12),
                    onChange: function (value) {
                        patchConfigs(gjsEditor, root, { limit: Number(value) || 0 });
                    },
                }));

                if (isGridLike(blockId)) {
                    form.fields.appendChild(createSelectField({
                        label: label(gjsEditor, 'vmediaColumns', 'Columns'),
                        name: 'columns',
                        value: String(config.columns || 3),
                        options: [1, 2, 3, 4, 5, 6].map(function (n) {
                            return { value: String(n), label: String(n) };
                        }),
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { columns: Number(value) || 3 });
                        },
                    }));

                    form.fields.appendChild(createSelectField({
                        label: label(gjsEditor, 'vmediaGap', 'Gap'),
                        name: 'gap',
                        value: String(config.gap || 'md'),
                        options: [
                            { value: 'sm', label: label(gjsEditor, 'vmediaGapSm', 'Compact') },
                            { value: 'md', label: label(gjsEditor, 'vmediaGapMd', 'Comfortable') },
                            { value: 'lg', label: label(gjsEditor, 'vmediaGapLg', 'Spacious') },
                        ],
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { gap: value });
                        },
                    }));

                    if (blockId === 'vmedia_gallery_grid') {
                        form.fields.appendChild(createSelectField({
                            label: label(gjsEditor, 'vmediaAspect', 'Aspect ratio'),
                            name: 'aspect',
                            value: String(config.aspect || '4/3'),
                            options: [
                                { value: '1/1', label: '1:1' },
                                { value: '4/3', label: '4:3' },
                                { value: '16/9', label: '16:9' },
                                { value: '3/4', label: '3:4' },
                                { value: 'auto', label: 'Auto' },
                            ],
                            onChange: function (value) {
                                patchConfigs(gjsEditor, root, { aspect: value });
                            },
                        }));
                    }
                }

                if (isMarquee(blockId)) {
                    form.fields.appendChild(createTextInputField({
                        label: label(gjsEditor, 'vmediaSpeed', 'Scroll speed (seconds)'),
                        name: 'speed',
                        type: 'number',
                        min: 8,
                        max: 120,
                        value: String(config.speed ?? 40),
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { speed: Number(value) || 40 });
                        },
                    }));

                    form.fields.appendChild(createSelectField({
                        label: label(gjsEditor, 'vmediaDirection', 'Direction'),
                        name: 'direction',
                        value: String(config.direction || 'left'),
                        options: [
                            { value: 'left', label: label(gjsEditor, 'vmediaDirectionLeft', 'Left') },
                            { value: 'right', label: label(gjsEditor, 'vmediaDirectionRight', 'Right') },
                        ],
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { direction: value });
                        },
                    }));

                    form.fields.appendChild(createToggleField({
                        label: label(gjsEditor, 'vmediaPauseOnHover', 'Pause on hover'),
                        name: 'pause_on_hover',
                        value: config.pause_on_hover !== false,
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { pause_on_hover: value });
                        },
                    }));
                }

                form.fields.appendChild(createToggleField({
                    label: label(gjsEditor, 'vmediaLightbox', 'Lightbox'),
                    name: 'lightbox',
                    value: config.lightbox !== false,
                    onChange: function (value) {
                        patchConfigs(gjsEditor, root, { lightbox: value });
                    },
                }));

                form.fields.appendChild(createToggleField({
                    label: label(gjsEditor, 'vmediaShowCaptions', 'Show captions'),
                    name: 'show_captions',
                    value: !! config.show_captions,
                    onChange: function (value) {
                        patchConfigs(gjsEditor, root, { show_captions: value });
                    },
                }));

                if (isGridLike(blockId)) {
                    form.fields.appendChild(createSelectField({
                        label: label(gjsEditor, 'vmediaCaptionPosition', 'Caption position'),
                        name: 'caption_position',
                        value: String(config.caption_position || 'below'),
                        options: [
                            { value: 'below', label: label(gjsEditor, 'vmediaCaptionBelow', 'Below image') },
                            { value: 'above', label: label(gjsEditor, 'vmediaCaptionAbove', 'Above image') },
                            { value: 'overlay', label: label(gjsEditor, 'vmediaCaptionOverlay', 'On image') },
                        ],
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { caption_position: value });
                        },
                    }));

                    form.fields.appendChild(createSelectField({
                        label: label(gjsEditor, 'vmediaCaptionBg', 'Caption background'),
                        name: 'caption_bg_color',
                        value: String(config.caption_bg_color || 'black'),
                        searchable: true,
                        options: captionColorOptions(gjsEditor, context),
                        onChange: function (value) {
                            patchCaptionStyle(gjsEditor, root, context, { caption_bg_color: value });
                        },
                    }));

                    form.fields.appendChild(createRangeField({
                        label: label(gjsEditor, 'vmediaCaptionBgOpacity', 'Caption opacity'),
                        name: 'caption_bg_opacity',
                        posLabel: '%',
                        min: 0,
                        max: 100,
                        step: 1,
                        value: Number(config.caption_bg_opacity ?? 82),
                        onInput: function (value) {
                            var color = String(readConfig(root).caption_bg_color || 'black');
                            livePaintCaptionBg(
                                root,
                                resolveCaptionBgCss(gjsEditor, context, color, value),
                            );
                        },
                        onChange: function (value) {
                            patchCaptionStyle(gjsEditor, root, context, { caption_bg_opacity: value });
                        },
                    }));

                    var captionHint = document.createElement('p');
                    captionHint.className = 'voodbuilder-editor-hint';
                    captionHint.textContent = label(
                        gjsEditor,
                        'vmediaCaptionHint',
                        'Captions and credits come from the media library. Gallery position/visibility override any per-image caption display.',
                    );
                    form.fields.appendChild(captionHint);
                }

                form.fields.appendChild(createToggleField({
                    label: label(gjsEditor, 'vmediaRounded', 'Rounded corners'),
                    name: 'rounded',
                    value: config.rounded !== false,
                    onChange: function (value) {
                        patchConfigs(gjsEditor, root, { rounded: value });
                    },
                }));

                settingsMount.appendChild(form.section);
            },
        });
    }

    var plugin = { id: 'vmedia', mount: mount };

    function tryRegister() {
        var api = window.VoodbuilderEditor;
        if (! api || typeof api.registerPlugin !== 'function') {
            return false;
        }
        api.registerPlugin(plugin);
        var editor = typeof api.getEditor === 'function' ? api.getEditor() : null;
        if (editor) {
            mount(editor, editor.__voodbuilderEditorConfig || {});
        }
        return true;
    }

    if (! tryRegister()) {
        window.VmediaEditorPlugin = plugin;
        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (tryRegister() || attempts > 40) {
                window.clearInterval(timer);
            }
        }, 250);
    }
})(window);
