<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Tiptap\Core\Extension;
use Voodflow\Vmedia\Filament\Forms\VmediaFilamentBrowser;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\AttachmentMeta;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\RichEditor\VmediaVaultFileAttachmentProvider;

final class VmediaRichContentPlugin implements HasFileAttachmentProvider, HasToolbarButtons, RichContentPlugin
{
    public function __construct(
        protected string $vaultPlugin = 'voodbuilder',
    ) {}

    public static function make(?string $vaultPlugin = null): self
    {
        return new self($vaultPlugin ?? 'voodbuilder');
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('vmediaAttach')
                ->label(__('vmedia::admin.editor.attach_from_library'))
                ->icon(Heroicon::Photo)
                ->action(arguments: '{ editorSelection: $getEditor()?.state?.selection ?? null }'),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        $vaultPlugin = $this->vaultPlugin;

        return [
            VmediaFilamentBrowser::pickAction(
                name: 'vmediaAttach',
                vaultGalleryId: fn (): int => VmediaFilamentBrowser::resolveVaultGalleryId($vaultPlugin),
                onPick: static fn (): null => null,
                multiple: false,
                accept: 'images',
            )->action(function (array $arguments, array $data, RichEditor $component): void {
                $raw = $data['media_uuids'] ?? null;
                $uuid = is_array($raw) ? ($raw[0] ?? null) : $raw;

                if (blank($uuid)) {
                    throw ValidationException::withMessages([
                        'media_uuids' => [__('vmedia::admin.picker.selection_required')],
                    ]);
                }

                $media = MediaItem::query()->where('uuid', (string) $uuid)->first();

                if ($media === null) {
                    return;
                }

                $url = MediaLibrary::publicUrl($media);
                $alt = AttachmentMeta::alt($media) ?? $media->alt() ?? $media->displayName();

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => [
                                'src' => $url,
                                'alt' => $alt,
                                'title' => $alt,
                            ],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.picker.selected'))
                    ->send();
            }),
        ];
    }

    public function getFileAttachmentProvider(): VmediaVaultFileAttachmentProvider
    {
        return new VmediaVaultFileAttachmentProvider($this->vaultPlugin);
    }

    /**
     * @return array<string>
     */
    public function getEnabledToolbarButtons(): array
    {
        return ['vmediaAttach'];
    }

    /**
     * @return array<string>
     */
    public function getDisabledToolbarButtons(): array
    {
        return [];
    }
}
