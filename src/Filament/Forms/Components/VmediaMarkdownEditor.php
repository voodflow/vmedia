<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
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

    public function toEmbeddedHtml(): string
    {
        if ($this->isDisabled()) {
            return parent::toEmbeddedHtml();
        }

        $this->extraAlpineAttributes([
            'data-vmedia-component-key' => (string) $this->getKey(),
            'data-vmedia-library-title' => (string) __('vmedia::admin.editor.attach_from_library'),
            'x-on:vmedia-markdown-insert.window' => $this->getMarkdownInsertAlpineHandler(),
        ]);

        $html = parent::toEmbeddedHtml();

        if (str_contains($html, 'setUpUsing:')) {
            return $html;
        }

        $needle = "                        },\n                    })\"";

        if (! str_contains($html, $needle)) {
            return $html;
        }

        return str_replace(
            $needle,
            "                        },\n                        setUpUsing: window.vmediaMarkdownEditorSetUp,\n                    })\"",
            $html,
        );
    }

    protected function getMarkdownInsertAlpineHandler(): string
    {
        $statePath = Js::from($this->getStatePath());

        return <<<JS
if (\$event.detail.statePath !== {$statePath}) {
    return
}

const cm = editor?.codemirror

if (! cm) {
    return
}

cm.replaceSelection(\$event.detail.markdown ?? '')
state = editor.value()
JS;
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
}
