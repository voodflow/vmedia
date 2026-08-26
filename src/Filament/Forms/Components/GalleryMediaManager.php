<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\FileTypeIcon;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\UploadGuard;
use Voodflow\Vmedia\Support\ZipImporter;

/**
 * Manage gallery membership on create/edit: upload, browse vault, reorder, edit meta, remove.
 * State is an ordered list of media UUIDs.
 */
class GalleryMediaManager extends Field
{
    protected string $view = 'vmedia::forms.components.gallery-media-manager';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->registerActions([
            fn (GalleryMediaManager $component): Action => $component->browseAction(),
            fn (GalleryMediaManager $component): Action => $component->uploadAction(),
            fn (GalleryMediaManager $component): Action => $component->importZipAction(),
            fn (GalleryMediaManager $component): Action => $component->editMediaAction(),
            fn (GalleryMediaManager $component): Action => $component->removeMediaAction(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function normalizedUuids(): array
    {
        $state = $this->getState();

        if (! is_array($state)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $state)));
    }

    /**
     * @return list<array{uuid: string, name: string, thumb: string|null, src: string, type: string, caption: string|null, alt: string|null}>
     */
    public function itemsPayload(): array
    {
        $uuids = $this->normalizedUuids();

        if ($uuids === []) {
            return [];
        }

        return MediaItem::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->sortBy(fn (MediaItem $media): int => array_search((string) $media->uuid, $uuids, true) ?: 0)
            ->map(function (MediaItem $media): array {
                $icon = FileTypeIcon::forMedia($media);

                return [
                    'uuid' => (string) $media->uuid,
                    'name' => $media->displayTitle(),
                    'thumb' => MediaLibrary::thumbUrl($media),
                    'src' => MediaLibrary::publicUrl($media),
                    'type' => MediaLibrary::assetType($media),
                    'icon' => $icon['icon'],
                    'icon_label' => $icon['icon_label'],
                    'caption' => $media->caption(),
                    'alt' => $media->alt(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'items' => $this->itemsPayload(),
            'componentKey' => $this->getKey(),
        ];
    }

    protected function browseAction(): Action
    {
        $manager = $this;
        $perPage = max(8, min(48, (int) config('vmedia.browser.picker_per_page', 20)));

        return Action::make('browseVault')
            ->label(fn (): string => __('vmedia::admin.galleries.media.browse'))
            ->icon('heroicon-o-photo')
            ->link()
            ->modalHeading(fn (): string => __('vmedia::admin.picker.modal_heading'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.picker.select'))
            ->modalWidth(Width::FiveExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->schema([
                Grid::make(2)->schema([
                    Select::make('gallery_id')
                        ->label(__('vmedia::admin.library.gallery'))
                        ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->nullable()
                        ->afterStateUpdated(fn (Set $set) => $set('browser_page', 1)),
                    TextInput::make('q')
                        ->label(__('vmedia::admin.picker.search'))
                        ->placeholder(__('vmedia::admin.picker.search_placeholder'))
                        ->live(debounce: 300)
                        ->nullable()
                        ->afterStateUpdated(fn (Set $set) => $set('browser_page', 1)),
                ]),
                Hidden::make('browser_page')->default(1)->live()->dehydrated(false),
                ViewField::make('media_uuids')
                    ->hiddenLabel()
                    ->default([])
                    ->view('vmedia::forms.components.media-browser-grid')
                    ->viewData(function (Get $get) use ($perPage): array {
                        $galleryId = $get('gallery_id');
                        $search = $get('q');
                        $page = max(1, (int) ($get('browser_page') ?? 1));

                        $result = MediaLibrary::paginateAssets(
                            null,
                            is_numeric($galleryId) ? (int) $galleryId : null,
                            is_string($search) && trim($search) !== '' ? trim($search) : null,
                            $page,
                            $perPage,
                        );

                        return [
                            'assets' => array_map(
                                static fn (array $asset): array => MediaLibrary::toBrowserTile($asset),
                                $result['data'],
                            ),
                            'meta' => $result['meta'],
                            'multiple' => true,
                        ];
                    }),
            ])
            ->fillForm(fn (): array => [
                'gallery_id' => (int) MediaGallery::default()->getKey(),
                'q' => null,
                'browser_page' => 1,
                'media_uuids' => [],
            ])
            ->action(function (array $data, Set $set) use ($manager): void {
                $raw = $data['media_uuids'] ?? [];
                $picked = is_array($raw)
                    ? array_values(array_filter(array_map('strval', $raw)))
                    : (filled($raw) ? [(string) $raw] : []);

                $merged = array_values(array_unique([...$manager->normalizedUuids(), ...$picked]));
                $set($manager->getStatePath(isAbsolute: false), $merged);

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.galleries.media.added'))
                    ->send();
            });
    }

    protected function uploadAction(): Action
    {
        $manager = $this;
        $mimes = array_merge(
            (array) config('vmedia.upload.allowed_image_mimes', []),
            (array) config('vmedia.upload.allowed_video_mimes', []),
            (array) config('vmedia.upload.allowed_file_mimes', []),
        );
        $extensions = (array) config('vmedia.upload.allowed_extensions', []);
        $maxKb = max(
            (int) config('vmedia.upload.image_max_kb', 8192),
            (int) config('vmedia.upload.video_max_kb', 51200),
            (int) config('vmedia.upload.file_max_kb', 20480),
        );

        return Action::make('uploadMedia')
            ->label(fn (): string => __('vmedia::admin.galleries.media.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->link()
            ->modalHeading(fn (): string => __('vmedia::admin.picker.upload_heading'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.picker.upload_submit'))
            ->schema([
                FileUpload::make('files')
                    ->label(__('vmedia::admin.library.files'))
                    ->multiple()
                    ->required()
                    ->storeFiles(false)
                    ->acceptedFileTypes($mimes)
                    ->rules([File::types($extensions)->max($maxKb)]),
            ])
            ->action(function (array $data, Set $set) use ($manager): void {
                $files = $data['files'] ?? [];

                if (! is_array($files)) {
                    $files = [$files];
                }

                $uuids = $manager->normalizedUuids();

                foreach ($files as $file) {
                    if (! $file instanceof TemporaryUploadedFile && ! $file instanceof UploadedFile) {
                        continue;
                    }

                    UploadGuard::assertSafeUpload($file);
                    $stored = MediaLibrary::store($file);
                    $uuids[] = (string) $stored->uuid;
                }

                $set($manager->getStatePath(isAbsolute: false), array_values(array_unique($uuids)));

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.picker.uploaded'))
                    ->send();
            });
    }

    protected function importZipAction(): Action
    {
        $manager = $this;
        $maxArchiveKb = max(1, (int) config('vmedia.zip.max_archive_kb', 51200));

        return Action::make('importZip')
            ->label(fn (): string => __('vmedia::admin.galleries.media.import_zip'))
            ->icon('heroicon-o-archive-box-arrow-down')
            ->link()
            ->modalHeading(fn (): string => __('vmedia::admin.library.import_zip'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.library.import_zip'))
            ->schema([
                FileUpload::make('zip')
                    ->label(__('vmedia::admin.library.zip_file'))
                    ->required()
                    ->storeFiles(false)
                    ->acceptedFileTypes([
                        'application/zip',
                        'application/x-zip-compressed',
                    ])
                    ->maxSize($maxArchiveKb)
                    ->helperText(__('vmedia::admin.galleries.media.import_zip_help')),
            ])
            ->action(function (array $data, Set $set) use ($manager): void {
                $zip = $data['zip'] ?? null;

                if (is_array($zip)) {
                    $zip = reset($zip);
                }

                if (! $zip instanceof TemporaryUploadedFile && ! $zip instanceof UploadedFile) {
                    return;
                }

                $result = ZipImporter::import($zip);
                $uuids = $manager->normalizedUuids();

                foreach ($result['items'] as $item) {
                    $uuids[] = (string) $item->uuid;
                }

                $set($manager->getStatePath(isAbsolute: false), array_values(array_unique($uuids)));

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.library.import_zip_done', [
                        'count' => $result['imported'],
                        'skipped' => $result['skipped'],
                    ]))
                    ->send();
            });
    }

    protected function editMediaAction(): Action
    {
        return Action::make('editGalleryMedia')
            ->label(fn (): string => __('vmedia::admin.library.edit'))
            ->modalHeading(fn (): string => __('vmedia::admin.galleries.media.edit_heading'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.picker.edit_item_save'))
            ->schema(function (Action $action): array {
                $uuid = (string) ($action->getArguments()['uuid'] ?? '');
                $media = $uuid !== ''
                    ? MediaItem::query()->where('uuid', $uuid)->first()
                    : null;
                $isImage = $media !== null && $media->isImage();
                $isVideo = $media !== null && $media->isVideo();

                return [
                    TextInput::make('name')
                        ->label(__('vmedia::admin.library.name'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('vmedia::admin.library.name_help')),
                    TextInput::make('alt')
                        ->label(__('vmedia::admin.library.alt'))
                        ->maxLength(255)
                        ->helperText(__('vmedia::admin.library.alt_help'))
                        ->visible($isImage),
                    Textarea::make('caption')
                        ->label(__('vmedia::admin.library.caption'))
                        ->rows(2)
                        ->maxLength(1000)
                        ->helperText(__('vmedia::admin.library.caption_help')),
                    TextInput::make('credits')
                        ->label(__('vmedia::admin.library.credits'))
                        ->maxLength(255)
                        ->helperText(__('vmedia::admin.library.credits_help')),
                    Select::make('poster_uuid')
                        ->label(__('vmedia::admin.library.poster'))
                        ->helperText(__('vmedia::admin.library.poster_help'))
                        ->searchable()
                        ->nullable()
                        ->visible($isVideo)
                        ->options(fn (): array => MediaItem::query()
                            ->where('collection_name', MediaGallery::COLLECTION_IMAGES)
                            ->orderByDesc('id')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (MediaItem $item): array => [
                                (string) $item->uuid => $item->displayTitle(),
                            ])
                            ->all()),
                    TextInput::make('file_name')
                        ->label(__('vmedia::admin.library.storage_name'))
                        ->disabled()
                        ->dehydrated(false),
                ];
            })
            ->fillForm(function (array $arguments): array {
                $uuid = (string) ($arguments['uuid'] ?? '');
                $media = MediaItem::query()->where('uuid', $uuid)->first();

                if ($media === null) {
                    return [];
                }

                return [
                    'name' => $media->displayTitle(),
                    'caption' => $media->caption(),
                    'alt' => $media->alt(),
                    'credits' => $media->credits(),
                    'poster_uuid' => $media->isVideo() ? $media->posterUuid() : null,
                    'file_name' => (string) $media->file_name,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $uuid = (string) ($arguments['uuid'] ?? '');
                $media = MediaItem::query()->where('uuid', $uuid)->first();

                if ($media === null) {
                    return;
                }

                $media->name = trim((string) ($data['name'] ?? $media->displayTitle()));
                $media->setCaption(isset($data['caption']) ? (string) $data['caption'] : null);
                $media->setCredits(isset($data['credits']) ? (string) $data['credits'] : null);

                if ($media->isImage()) {
                    $media->setAlt(isset($data['alt']) ? (string) $data['alt'] : null);
                }

                if ($media->isVideo()) {
                    $media->setPosterUuid(isset($data['poster_uuid']) ? (string) $data['poster_uuid'] : null);
                }

                $media->save();

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.library.meta_saved'))
                    ->send();
            });
    }

    protected function removeMediaAction(): Action
    {
        $manager = $this;

        return Action::make('removeGalleryMedia')
            ->label(fn (): string => __('vmedia::admin.galleries.media.remove'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('vmedia::admin.galleries.media.remove_heading'))
            ->modalDescription(fn (): string => __('vmedia::admin.galleries.media.remove_help'))
            ->action(function (array $arguments, Set $set) use ($manager): void {
                $uuid = (string) ($arguments['uuid'] ?? '');
                $uuids = array_values(array_filter(
                    $manager->normalizedUuids(),
                    fn (string $item): bool => $item !== $uuid,
                ));
                $set($manager->getStatePath(isAbsolute: false), $uuids);
            });
    }
}
