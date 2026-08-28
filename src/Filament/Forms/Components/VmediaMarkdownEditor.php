<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Voodflow\Vmedia\Filament\Forms\VmediaFilamentBrowser;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\AttachmentMeta;
use Voodflow\Vmedia\Support\MediaLibrary;

/**
 * Markdown editor with an extra toolbar button to insert images from the vmedia library.
 * Native {@see MarkdownEditor} file attachments stay available when {@see attachFiles} is in the toolbar.
 */
class VmediaMarkdownEditor extends MarkdownEditor
{
    protected string|Closure $vaultPlugin = 'voodbuilder';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (VmediaMarkdownEditor $component): Action => $component->insertVmediaImageAction(),
        ]);
    }

    public function vaultPlugin(string|Closure $plugin): static
    {
        $this->vaultPlugin = $plugin;

        return $this;
    }

    public function getVaultPlugin(): string
    {
        return (string) $this->evaluate($this->vaultPlugin);
    }

    protected function insertVmediaImageAction(): Action
    {
        $component = $this;

        return VmediaFilamentBrowser::pickAction(
            name: 'insertVmediaImage',
            defaultVaultGalleryId: fn (): int => VmediaFilamentBrowser::resolveVaultGalleryId($component->getVaultPlugin()),
            onPick: function (array $result) use ($component): void {
                /** @var Collection<int, MediaItem> $media */
                $media = $result['media'];

                $chunks = $media
                    ->map(function (MediaItem $item): string {
                        $url = MediaLibrary::publicUrl($item);
                        $alt = AttachmentMeta::alt($item) ?? $item->alt() ?? $item->displayName();

                        return '!['.str_replace(['[', ']'], ['\\[', '\\]'], (string) $alt).']('.$url.')';
                    })
                    ->all();

                if ($chunks === []) {
                    return;
                }

                $component->getLivewire()->dispatch(
                    'vmedia-markdown-insert',
                    statePath: $component->getStatePath(),
                    markdown: implode("\n\n", $chunks),
                );
            },
            multiple: true,
            accept: 'images',
        );
    }

    public function toEmbeddedHtml(): string
    {
        $id = $this->getId();
        $isDisabled = $this->isDisabled();
        $statePath = $this->getStatePath();

        if ($isDisabled) {
            ob_start(); ?>

            <div aria-labelledby="<?= e($id) ?>-label" id="<?= e($id) ?>" role="group" class="fi-fo-markdown-editor fi-disabled fi-prose">
                <?= str($this->getState())->markdown($this->getCommonMarkOptions(), $this->getCommonMarkExtensions())->sanitizeHtml() ?>
            </div>

            <?php return $this->wrapEmbeddedHtml(ob_get_clean(), labelTag: 'div');
        }

        $key = $this->getKey();
        $label = $this->getLabel();
        $fileAttachmentsMaxSize = $this->getFileAttachmentsMaxSize();
        $fileAttachmentsAcceptedFileTypes = $this->getFileAttachmentsAcceptedFileTypes();

        $wrapperAttributes = $this->getExtraAttributeBag()
            ->class(['fi-fo-markdown-editor']);

        ob_start(); ?>

        <link rel="stylesheet" href="<?= e(FilamentAsset::getStyleHref('vmedia-markdown-editor', 'voodflow/vmedia')) ?>" data-vmedia-markdown-editor-styles />

        <div
            aria-labelledby="<?= e($id) ?>-label"
            id="<?= e($id) ?>"
            role="group"
            x-load
            x-load-src="<?= e(FilamentAsset::getAlpineComponentSrc('markdown-editor', 'filament/forms')) ?>"
            x-data="markdownEditorFormComponent({
                        canAttachFiles: <?= Js::from($this->hasFileAttachments()) ?>,
                        isLiveDebounced: <?= Js::from($this->isLiveDebounced()) ?>,
                        isLiveOnBlur: <?= Js::from($this->isLiveOnBlur()) ?>,
                        label: <?= Js::from($label) ?>,
                        liveDebounce: <?= Js::from($this->getNormalizedLiveDebounce()) ?>,
                        maxHeight: <?= Js::from($this->getMaxHeight()) ?>,
                        minHeight: <?= Js::from($this->getMinHeight()) ?>,
                        placeholder: <?= Js::from($this->getPlaceholder()) ?>,
                        state: $wire.<?= $this->applyStateBindingModifiers("\$entangle('{$statePath}')", isOptimisticallyLive: false) ?>,
                        toolbarButtons: <?= Js::from($this->getToolbarButtons()) ?>,
                        translations: <?= Js::from(__('filament-forms::components.markdown_editor')) ?>,
                        uploadFileAttachmentUsing: async (file, onSuccess, onError) => {
                            const acceptedTypes = <?= Js::from($fileAttachmentsAcceptedFileTypes) ?>

                            if (acceptedTypes && ! acceptedTypes.includes(file.type)) {
                                return onError(<?= Js::from($fileAttachmentsAcceptedFileTypes ? __('filament-forms::components.markdown_editor.file_attachments_accepted_file_types_message', ['values' => implode(', ', $fileAttachmentsAcceptedFileTypes)]) : null) ?>)
                            }

                            const maxSize = <?= Js::from($fileAttachmentsMaxSize) ?>

                            if (maxSize && file.size > +maxSize * 1024) {
                                return onError(<?= Js::from($fileAttachmentsMaxSize ? trans_choice('filament-forms::components.markdown_editor.file_attachments_max_size_message', $fileAttachmentsMaxSize, ['max' => $fileAttachmentsMaxSize]) : null) ?>)
                            }

                            $wire.upload(`componentFileAttachments.<?= e($statePath) ?>`, file, () => {
                                $wire
                                    .callSchemaComponentMethod(
                                        <?= Js::from($key) ?>,
                                        'saveUploadedFileAttachmentAndGetUrl',
                                    )
                                    .then((url) => {
                                        if (! url) {
                                            return onError()
                                        }

                                        onSuccess(url)
                                    })
                            })
                        },
                        setUpUsing: (editorComponent) => {
                            const wire = editorComponent.$wire
                            const componentKey = <?= Js::from($key) ?>
                            const libraryTitle = <?= Js::from(__('vmedia::admin.editor.attach_from_library')) ?>

                            const openLibrary = (event) => {
                                event.preventDefault()
                                wire.mountFormComponentAction(componentKey, 'insertVmediaImage')
                            }

                            const bar = editorComponent.editor?.gui?.toolbar

                            if (! bar || bar.querySelector('.vmedia-library')) {
                                return
                            }

                            const separator = document.createElement('span')
                            separator.className = 'separator'
                            bar.appendChild(separator)

                            const button = document.createElement('button')
                            button.type = 'button'
                            button.className = 'vmedia-library'
                            button.title = libraryTitle
                            button.setAttribute('aria-label', libraryTitle)
                            button.addEventListener('click', openLibrary)
                            bar.appendChild(button)
                        },
                    })"
            x-on:vmedia-markdown-insert.window="
                if ($event.detail.statePath !== <?= Js::from($statePath) ?>) {
                    return
                }

                const cm = editor?.codemirror

                if (! cm) {
                    return
                }

                cm.replaceSelection($event.detail.markdown ?? '')
                state = editor.value()
            "
            wire:ignore
            <?= $this->getExtraAlpineAttributeBag()->toHtml() ?>
        >
            <textarea x-ref="editor" x-cloak></textarea>
        </div>

        <?php $slotHtml = ob_get_clean();

        return $this->wrapEmbeddedHtml(
            $this->wrapInputHtml(
                $slotHtml,
                attributes: $wrapperAttributes,
            ),
            labelTag: 'div',
        );
    }
}
