<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\GalleryBrowser;
use Voodflow\Vmedia\Support\GalleryUploadTarget;
use Voodflow\Vmedia\Support\Integration\PluginVaultLibraryGallery;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\UploadGuard;

/**
 * Shared vmedia browse/upload modal for Filament markdown & rich editors.
 */
final class VmediaFilamentBrowser
{
    /**
     * @param  Closure(): int|null  $defaultVaultGalleryId  Suggested browse/upload default (e.g. plugin library album)
     * @param  Closure(array<string, mixed>): void  $onPick
     */
    public static function pickAction(
        string $name,
        Closure $defaultVaultGalleryId,
        Closure $onPick,
        bool $multiple = false,
        string $accept = 'images',
    ): Action {
        return Action::make($name)
            ->modalHeading(__('vmedia::admin.editor.modal_heading'))
            ->modalSubmitActionLabel(__('vmedia::admin.picker.select'))
            ->modalWidth(Width::FiveExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->schema(fn (): array => self::schema($defaultVaultGalleryId, $multiple, $accept))
            ->fillForm(function () use ($defaultVaultGalleryId, $multiple): array {
                $galleryId = $defaultVaultGalleryId();
                $filters = GalleryBrowser::defaultPickerFilters($galleryId);

                return [
                    'parent_folder_id' => $filters['parent_folder_id'],
                    'gallery_id' => $filters['gallery_id'],
                    'q' => null,
                    'browser_page' => 1,
                    'browser_nonce' => 0,
                    'upload_files' => [],
                    'media_uuids' => $multiple ? [] : null,
                ];
            })
            ->action(function (array $data) use ($onPick): void {
                $raw = $data['media_uuids'] ?? null;
                $uuids = is_array($raw)
                    ? array_values(array_filter(array_map('strval', $raw)))
                    : (filled($raw) ? [(string) $raw] : []);

                if ($uuids === []) {
                    throw ValidationException::withMessages([
                        'media_uuids' => [__('vmedia::admin.picker.selection_required')],
                    ]);
                }

                $media = MediaItem::query()
                    ->whereIn('uuid', $uuids)
                    ->get()
                    ->sortBy(fn (MediaItem $item): int => array_search((string) $item->uuid, $uuids, true))
                    ->values();

                $onPick([
                    'uuids' => $uuids,
                    'media' => $media,
                ]);

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.picker.selected'))
                    ->send();
            });
    }

    /**
     * @param  Closure(): int|null  $defaultVaultGalleryId
     * @return list<Component>
     */
    public static function schema(Closure $defaultVaultGalleryId, bool $multiple, string $accept): array
    {
        $perPage = max(8, min(48, (int) config('vmedia.browser.picker_per_page', 20)));

        return array_values(array_filter([
            self::uploadField($defaultVaultGalleryId, $multiple, $accept),
            Grid::make(3)->schema(GalleryBrowser::filterFields()),
            Hidden::make('browser_page')
                ->default(1)
                ->live()
                ->dehydrated(false),
            Hidden::make('browser_nonce')
                ->default(0)
                ->live()
                ->dehydrated(false),
            ViewField::make('media_uuids')
                ->hiddenLabel()
                ->live()
                ->view('vmedia::forms.components.media-browser-grid')
                ->viewData(function (Get $get) use ($defaultVaultGalleryId, $perPage, $multiple): array {
                    $get('browser_nonce');

                    $parentFolderId = is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null;
                    $galleryId = is_numeric($get('gallery_id')) ? (int) $get('gallery_id') : null;
                    $search = $get('q');
                    $page = max(1, (int) ($get('browser_page') ?? 1));

                    $result = GalleryBrowser::paginateForPicker(
                        type: 'image',
                        parentFolderId: $parentFolderId,
                        galleryId: $galleryId,
                        search: is_string($search) && trim($search) !== '' ? trim($search) : null,
                        page: $page,
                        perPage: $perPage,
                    );

                    $uploadTarget = GalleryUploadTarget::payload(
                        $parentFolderId,
                        $galleryId,
                        $defaultVaultGalleryId(),
                    );

                    return [
                        'assets' => array_map(
                            static fn (array $asset): array => MediaLibrary::toBrowserTile($asset),
                            $result['data'],
                        ),
                        'meta' => $result['meta'],
                        'multiple' => $multiple,
                        'upload_target' => $uploadTarget,
                    ];
                }),
        ]));
    }

    /**
     * @param  Closure(): int|null  $defaultVaultGalleryId
     */
    protected static function uploadField(Closure $defaultVaultGalleryId, bool $multiple, string $accept): FileUpload
    {
        $mimes = match ($accept) {
            'videos' => ['video/mp4', 'video/webm', 'video/quicktime'],
            'files' => null,
            default => ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'],
        };

        $extensions = match ($accept) {
            'videos' => ['mp4', 'webm', 'mov'],
            'files' => ['pdf', 'doc', 'docx', 'zip'],
            default => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg'],
        };

        $maxKb = max(
            (int) config('vmedia.upload.image_max_kb', 8192),
            (int) config('vmedia.upload.video_max_kb', 51200),
            (int) config('vmedia.upload.file_max_kb', 20480),
        );

        return FileUpload::make('upload_files')
            ->label(__('vmedia::admin.picker.upload_in_modal'))
            ->helperText(function (Get $get) use ($defaultVaultGalleryId): string {
                $target = GalleryUploadTarget::payload(
                    is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null,
                    is_numeric($get('gallery_id')) ? (int) $get('gallery_id') : null,
                    $defaultVaultGalleryId(),
                );

                return __('vmedia::admin.picker.upload_destination_help', [
                    'path' => $target['path'] !== '' ? $target['path'] : $target['name'],
                ]);
            })
            ->multiple($multiple)
            ->storeFiles(false)
            ->dehydrated(false)
            ->panelLayout('compact')
            ->live()
            ->acceptedFileTypes($mimes)
            ->rules([
                File::types($extensions)->max($maxKb),
            ])
            ->afterStateUpdated(function (mixed $state, Set $set, Get $get) use ($defaultVaultGalleryId, $multiple): void {
                if (blank($state)) {
                    return;
                }

                $target = GalleryUploadTarget::resolveFromBrowse(
                    is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null,
                    is_numeric($get('gallery_id')) ? (int) $get('gallery_id') : null,
                    $defaultVaultGalleryId(),
                );

                $uuids = self::storeFilesToVault($state, $target);

                if ($uuids === []) {
                    $set('upload_files', []);

                    return;
                }

                $raw = $get('media_uuids');
                $current = is_array($raw)
                    ? array_values(array_filter(array_map('strval', $raw)))
                    : (filled($raw) ? [(string) $raw] : []);

                if ($multiple) {
                    $set('media_uuids', array_values(array_unique([...$current, ...$uuids])));
                } else {
                    $set('media_uuids', $uuids[0]);
                }

                $set('upload_files', []);
                $set('browser_page', 1);
                $set('browser_nonce', ((int) ($get('browser_nonce') ?? 0)) + 1);
            })
            ->columnSpanFull()
            ->extraAttributes(['class' => 'vmedia-picker-instant-upload']);
    }

    /**
     * @return list<string>
     */
    protected static function storeFilesToVault(mixed $files, MediaGallery $targetGallery): array
    {
        if (! is_array($files)) {
            $files = filled($files) ? [$files] : [];
        }

        $uuids = [];

        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile && ! $file instanceof UploadedFile) {
                continue;
            }

            UploadGuard::assertSafeUpload($file);
            $stored = MediaLibrary::store($file, $targetGallery);
            $uuids[] = (string) $stored->uuid;
        }

        return array_values(array_unique($uuids));
    }

    public static function resolveVaultGalleryId(string $vaultPlugin): int
    {
        return (int) PluginVaultLibraryGallery::album($vaultPlugin)->getKey();
    }
}
