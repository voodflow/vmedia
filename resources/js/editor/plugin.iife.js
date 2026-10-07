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

    function parseBlockConfig(raw) {
        if (! raw || typeof raw !== 'string') {
            return {};
        }
        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' && ! Array.isArray(parsed) ? parsed : {};
        } catch (err) {
            return {};
        }
    }

    function encodeBlockConfig(config) {
        try {
            return JSON.stringify(config || {});
        } catch (err) {
            return '{}';
        }
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
        return fromModel && typeof fromModel === 'object'
            ? Object.assign({}, fromModel)
            : parseBlockConfig(attrs['data-voodbuilder-config'] || '{}');
    }

    function persistConfig(editor, root, config) {
        if (root.set) {
            root.set('voodbuilderConfig', config, { silent: true });
        }
        if (root.addAttributes) {
            root.addAttributes({
                'data-voodbuilder-config': encodeBlockConfig(config),
            });
        }
        if (editor && editor.__voodbuilderTrackSaveDirty !== false) {
            editor.__voodbuilderPageSaveClean = false;
        }
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

    function injectGalleryCanvasCss(editor) {
        var link = document.querySelector('link[data-vmedia-gallery-css]');
        var inline = document.querySelector('style[data-vmedia-gallery-css]');
        var href = link ? link.href : null;

        function into(doc) {
            if (! doc || ! doc.head || doc.getElementById('vmedia-gallery-canvas-css')) {
                return;
            }

            if (inline && inline.textContent) {
                var style = doc.createElement('style');
                style.id = 'vmedia-gallery-canvas-css';
                style.setAttribute('data-vmedia-gallery-css', '1');
                style.textContent = inline.textContent;
                doc.head.appendChild(style);
                return;
            }

            if (href) {
                var cloned = doc.createElement('link');
                cloned.id = 'vmedia-gallery-canvas-css';
                cloned.rel = 'stylesheet';
                cloned.href = href;
                cloned.setAttribute('data-vmedia-gallery-css', '1');
                doc.head.appendChild(cloned);
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
            });
        }
    }

    function scheduleFullRefresh(editor, root) {
        if (! refreshTimers) {
            editor.trigger('voodbuilder:refresh-dynamic-block', root);
            window.setTimeout(function () { bumpPageCss(editor); }, 220);
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
            window.setTimeout(function () { bumpPageCss(editor); }, 220);
        }, 160));
    }

    function writeConfig(editor, root, config) {
        persistConfig(editor, root, config);
        scheduleFullRefresh(editor, root);
    }

    function patchConfigs(editor, root, patch) {
        var next = Object.assign({}, readConfig(root), patch);
        writeConfig(editor, root, next);
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
        (options.options || []).forEach(function (opt) {
            var option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
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
                window.setTimeout(function () { bumpPageCss(editor); }, 900);
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
                        });
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

                    form.fields.appendChild(createTextInputField({
                        label: label(gjsEditor, 'vmediaCaptionBg', 'Caption background'),
                        name: 'caption_bg',
                        value: String(config.caption_bg || 'rgba(0, 0, 0, 0.72)'),
                        onChange: function (value) {
                            patchConfigs(gjsEditor, root, { caption_bg: value });
                        },
                    }));
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
