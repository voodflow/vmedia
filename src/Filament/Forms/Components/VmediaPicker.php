<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\AttachmentMeta;
use Voodflow\Vmedia\Support\FileTypeIcon;
use Voodflow\Vmedia\Support\GalleryBrowser;
use Voodflow\Vmedia\Support\GalleryUploadTarget;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\UploadGuard;

/**
 * Pick existing vault media (single or multiple) into a HasAttachedMedia collection,
 * or dehydrate UUIDs when used as a standalone field. Optional upload into the vault.
 *
 * State items: `{uuid, caption?, alt?, credits?}` — overrides are per-attachment, not vault-global.
 */
class VmediaPicker extends Field
{
    protected string $view = 'vmedia::forms.components.vmedia-picker';

    protected string|Closure|null $collection = 'default';

    /**
     * images | videos | files | any
     */
    protected string|Closure $accept = 'images';

    protected bool|Closure $attachToRecord = true;

    protected bool|Closure $isMultiple = false;

    protected bool|Closure $uploadEnabled = true;

    protected int|Closure|null $vaultGalleryId = null;

    protected bool|Closure $vaultGalleryLocked = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (): ?array => $this->isMultiple() ? [] : null);

        $this->registerActions([
            fn (VmediaPicker $component): Action => $component->editAttachmentMetaAction(),
            fn (VmediaPicker $component): Action => $component->removeAttachmentAction(),
        ]);

        $this->loadStateFromRelationshipsUsing(static function (VmediaPicker $component, ?Model $record): void {
            if ($record === null || ! $component->shouldAttachToRecord() || ! self::usesAttachedMedia($record)) {
                return;
            }

            $items = $record->load('media')
                ->getMedia($component->getCollection() ?? 'default')
                ->when(
                    ! $component->isMultiple(),
                    fn ($media) => $media->take(1),
                )
                ->map(static function (MediaItem $media): array {
                    $overrides = AttachmentMeta::overrides($media);

                    return [
                        'uuid' => (string) $media->uuid,
                        'caption' => $overrides['caption'] ?? null,
                        'alt' => $overrides['alt'] ?? null,
                        'credits' => $overrides['credits'] ?? null,
                    ];
                })
                ->values()
                ->all();

            $component->state($component->isMultiple() ? $items : ($items[0] ?? null));
        });

        $this->saveRelationshipsUsing(static function (VmediaPicker $component, ?Model $record): void {
            if ($record === null || ! $component->shouldAttachToRecord() || ! self::usesAttachedMedia($record)) {
                return;
            }

            $items = $component->normalizedItems();
            $uuids = array_column($items, 'uuid');
            $byUuid = MediaItem::query()
                ->whereIn('uuid', $uuids)
                ->get()
                ->keyBy(fn (MediaItem $media): string => (string) $media->uuid);

            $allowedGalleryId = $component->getVaultGalleryId();
            $existingUuids = [];

            if ($record !== null && self::usesAttachedMedia($record)) {
                $existingUuids = $record->load('media')
                    ->getMedia($component->getCollection() ?? 'default')
                    ->map(fn (MediaItem $media): string => (string) $media->uuid)
                    ->all();
            }

            $entries = [];

            foreach ($items as $item) {
                $media = $byUuid->get($item['uuid']);

                if ($media === null) {
                    continue;
                }

                if (
                    $allowedGalleryId !== null
                    && ! in_array($item['uuid'], $existingUuids, true)
                    && ! $media->galleries()->whereKey($allowedGalleryId)->exists()
                ) {
                    continue;
                }

                $entries[] = [
                    'id' => (int) $media->getKey(),
                    'properties' => AttachmentMeta::normalizeProperties([
                        'caption' => $item['caption'] ?? null,
                        'alt' => $item['alt'] ?? null,
                        'credits' => $item['credits'] ?? null,
                    ]),
                ];
            }

            $record->syncMediaCollection($component->getCollection() ?? 'default', $entries);
        });

        $this->dehydrated(fn (VmediaPicker $component): bool => ! $component->shouldAttachToRecord());

        $this->hintActions([
            $this->browseAction(),
            $this->clearAction(),
        ]);
    }

    protected function editAttachmentMetaAction(): Action
    {
        return Action::make('editAttachmentMeta')
            ->label(fn (): string => __('vmedia::admin.picker.edit_item'))
            ->modalHeading(fn (): string => __('vmedia::admin.picker.edit_item_heading'))
            ->modalDescription(fn (): string => __('vmedia::admin.picker.edit_item_help'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.picker.edit_item_save'))
            ->schema(function (Action $action): array {
                $uuid = (string) ($action->getArguments()['uuid'] ?? '');
                $media = $uuid !== ''
                    ? MediaItem::query()->where('uuid', $uuid)->first()
                    : null;

                $vaultCaption = $media?->caption();
                $vaultAlt = $media?->alt();
                $vaultCredits = $media?->credits();
                $isImage = $media !== null && MediaLibrary::assetType($media) === 'image';

                return [
                    Textarea::make('caption')
                        ->label(__('vmedia::admin.library.caption'))
                        ->rows(2)
                        ->placeholder($vaultCaption ?: __('vmedia::admin.picker.override_placeholder'))
                        ->helperText(__('vmedia::admin.picker.override_caption_help')),
                    TextInput::make('alt')
                        ->label(__('vmedia::admin.library.alt'))
                        ->visible($isImage)
                        ->placeholder($vaultAlt ?: __('vmedia::admin.picker.override_placeholder'))
                        ->helperText(__('vmedia::admin.picker.override_alt_help')),
                    TextInput::make('credits')
                        ->label(__('vmedia::admin.library.credits'))
                        ->placeholder($vaultCredits ?: __('vmedia::admin.picker.override_placeholder'))
                        ->helperText(__('vmedia::admin.picker.override_credits_help')),
                ];
            })
            ->fillForm(function (array $arguments): array {
                $uuid = (string) ($arguments['uuid'] ?? '');

                foreach ($this->normalizedItems() as $item) {
                    if ($item['uuid'] === $uuid) {
                        return [
                            'caption' => $item['caption'],
                            'alt' => $item['alt'],
                            'credits' => $item['credits'],
                        ];
                    }
                }

                return [
                    'caption' => null,
                    'alt' => null,
                    'credits' => null,
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $uuid = (string) ($arguments['uuid'] ?? '');

                if ($uuid === '') {
                    return;
                }

                $items = $this->normalizedItems();
                $found = false;

                foreach ($items as $index => $item) {
                    if ($item['uuid'] !== $uuid) {
                        continue;
                    }

                    $items[$index] = [
                        'uuid' => $uuid,
                        'caption' => filled($data['caption'] ?? null) ? trim((string) $data['caption']) : null,
                        'alt' => filled($data['alt'] ?? null) ? trim((string) $data['alt']) : null,
                        'credits' => filled($data['credits'] ?? null) ? trim((string) $data['credits']) : null,
                    ];
                    $found = true;
                    break;
                }

                if (! $found) {
                    return;
                }

                $this->state($this->isMultiple() ? array_values($items) : ($items[0] ?? null));

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.picker.item_meta_saved'))
                    ->send();
            });
    }

    protected function removeAttachmentAction(): Action
    {
        return Action::make('removeAttachment')
            ->label(fn (): string => __('vmedia::admin.picker.detach'))
            ->color('danger')
            ->action(function (array $arguments): void {
                $uuid = (string) ($arguments['uuid'] ?? '');

                if ($uuid === '') {
                    return;
                }

                if (! $this->isMultiple()) {
                    $this->state(null);

                    return;
                }

                $items = array_values(array_filter(
                    $this->normalizedItems(),
                    fn (array $item): bool => $item['uuid'] !== $uuid,
                ));

                $this->state($items);
            });
    }

    protected function browseAction(): Action
    {
        return Action::make('browseLibrary')
            ->label(fn (): string => __('vmedia::admin.picker.choose_media'))
            ->icon('heroicon-o-photo')
            ->link()
            ->modalHeading(fn (): string => __('vmedia::admin.picker.modal_heading'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.picker.select'))
            ->modalWidth(Width::FiveExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->schema(function (): array {
                $picker = $this;
                $perPage = max(8, min(48, (int) config('vmedia.browser.picker_per_page', 20)));
                $lockedGallery = $picker->isVaultGalleryLocked();
                $uploadField = $picker->isUploadEnabled()
                    ? $picker->browseUploadField()
                    : null;

                return array_values(array_filter([
                    $uploadField,
                    Grid::make($lockedGallery ? 1 : 3)->schema(GalleryBrowser::filterFields($lockedGallery)),
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
                        ->viewData(function (Get $get) use ($picker, $perPage): array {
                            $get('browser_nonce');

                            $parentFolderId = is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null;
                            $galleryId = is_numeric($get('gallery_id')) ? (int) $get('gallery_id') : null;
                            $search = $get('q');
                            $page = max(1, (int) ($get('browser_page') ?? 1));

                            $result = $picker->browserPage(
                                $galleryId,
                                is_string($search) && trim($search) !== '' ? trim($search) : null,
                                $page,
                                $perPage,
                                $parentFolderId,
                            );

                            return [
                                'assets' => $result['assets'],
                                'meta' => $result['meta'],
                                'multiple' => $picker->isMultiple(),
                                'upload_target' => GalleryUploadTarget::payload(
                                    $parentFolderId,
                                    $galleryId,
                                    $picker->getVaultGalleryId(),
                                ),
                            ];
                        }),
                ]));
            })
            ->fillForm(function (): array {
                $uuids = $this->normalizedUuids();
                $filters = GalleryBrowser::defaultPickerFilters($this->getVaultGalleryId());

                return [
                    'parent_folder_id' => $filters['parent_folder_id'],
                    'gallery_id' => $filters['gallery_id'],
                    'q' => null,
                    'browser_page' => 1,
                    'upload_files' => [],
                    'media_uuids' => $this->isMultiple() ? $uuids : ($uuids[0] ?? null),
                ];
            })
            ->action(function (array $data, Set $set): void {
                $raw = $data['media_uuids'] ?? null;
                $uuids = is_array($raw)
                    ? array_values(array_filter(array_map('strval', $raw)))
                    : (filled($raw) ? [(string) $raw] : []);

                if ($uuids === []) {
                    throw ValidationException::withMessages([
                        'media_uuids' => [__('vmedia::admin.picker.selection_required')],
                    ]);
                }

                $this->applyUuidsToState($set, $uuids, replace: true);

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.picker.selected'))
                    ->send();
            });
    }

    protected function browseUploadField(): FileUpload
    {
        $picker = $this;
        $mimes = $this->acceptedMimeTypes();
        $extensions = $this->acceptedExtensions();
        $maxKb = max(
            (int) config('vmedia.upload.image_max_kb', 8192),
            (int) config('vmedia.upload.video_max_kb', 51200),
            (int) config('vmedia.upload.file_max_kb', 20480),
        );

        return FileUpload::make('upload_files')
            ->label(__('vmedia::admin.picker.upload_in_modal'))
            ->helperText(function (Get $get) use ($picker): string {
                if ($picker->isVaultGalleryLocked()) {
                    return __('vmedia::admin.picker.upload_in_modal_help');
                }

                $target = GalleryUploadTarget::payload(
                    is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null,
                    is_numeric($get('gallery_id')) ? (int) $get('gallery_id') : null,
                    $picker->getVaultGalleryId(),
                );

                return __('vmedia::admin.picker.upload_destination_help', [
                    'path' => $target['path'] !== '' ? $target['path'] : $target['name'],
                ]);
            })
            ->multiple($this->isMultiple())
            ->storeFiles(false)
            ->dehydrated(false)
            ->panelLayout('compact')
            ->live()
            ->acceptedFileTypes($mimes)
            ->rules([
                File::types($extensions)->max($maxKb),
            ])
            ->afterStateUpdated(function (mixed $state, Set $set, Get $get) use ($picker): void {
                if (blank($state)) {
                    return;
                }

                $uploadUuids = $picker->storeFilesToVault($state, $get);

                if ($uploadUuids === []) {
                    $set('upload_files', []);

                    return;
                }

                $raw = $get('media_uuids');
                $current = is_array($raw)
                    ? array_values(array_filter(array_map('strval', $raw)))
                    : (filled($raw) ? [(string) $raw] : []);

                if ($picker->isMultiple()) {
                    $set('media_uuids', array_values(array_unique([...$current, ...$uploadUuids])));
                } else {
                    $set('media_uuids', $uploadUuids[0]);
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
    protected function storeFilesToVault(mixed $files, ?Get $get = null): array
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
            $gallery = $this->resolveUploadGallery($get);
            $stored = $gallery !== null
                ? MediaLibrary::store($file, $gallery)
                : MediaLibrary::store($file);
            $uuids[] = (string) $stored->uuid;
        }

        return array_values(array_unique($uuids));
    }

    protected function uploadAction(): Action
    {
        return Action::make('uploadToVault')
            ->label(fn (): string => __('vmedia::admin.picker.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->link()
            ->visible(fn (): bool => $this->isUploadEnabled())
            ->modalHeading(fn (): string => __('vmedia::admin.picker.upload_heading'))
            ->modalSubmitActionLabel(fn (): string => __('vmedia::admin.picker.upload_submit'))
            ->schema(function (): array {
                $mimes = $this->acceptedMimeTypes();
                $extensions = $this->acceptedExtensions();
                $maxKb = max(
                    (int) config('vmedia.upload.image_max_kb', 8192),
                    (int) config('vmedia.upload.video_max_kb', 51200),
                    (int) config('vmedia.upload.file_max_kb', 20480),
                );

                return [
                    FileUpload::make('files')
                        ->label(__('vmedia::admin.library.files'))
                        ->multiple($this->isMultiple())
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes($mimes)
                        ->rules([
                            File::types($extensions)->max($maxKb),
                        ]),
                ];
            })
            ->action(function (array $data, Set $set): void {
                $uuids = array_values(array_unique([
                    ...$this->storeFilesToVault($data['files'] ?? []),
                    ...$this->normalizedUuids(),
                ]));

                $this->applyUuidsToState($set, $uuids, replace: true);

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.picker.uploaded'))
                    ->send();
            });
    }

    protected function clearAction(): Action
    {
        return Action::make('clearSelection')
            ->label(fn (): string => __('vmedia::admin.picker.clear'))
            ->icon('heroicon-o-x-mark')
            ->link()
            ->color('danger')
            ->visible(fn (): bool => $this->normalizedUuids() !== [])
            ->requiresConfirmation()
            ->action(function (Set $set): void {
                $this->applyUuidsToState($set, [], replace: true);
            });
    }

    /**
     * @param  list<string>  $uuids
     */
    protected function applyUuidsToState(Set $set, array $uuids, bool $replace = true): void
    {
        $path = $this->getStatePath(isAbsolute: false);
        $existing = collect($this->normalizedItems())->keyBy('uuid');

        if (! $replace) {
            $uuids = array_values(array_unique([...$this->normalizedUuids(), ...$uuids]));
        }

        $items = [];

        foreach ($uuids as $uuid) {
            $uuid = (string) $uuid;

            if ($uuid === '') {
                continue;
            }

            $prev = $existing->get($uuid);

            $items[] = [
                'uuid' => $uuid,
                'caption' => is_array($prev) ? ($prev['caption'] ?? null) : null,
                'alt' => is_array($prev) ? ($prev['alt'] ?? null) : null,
                'credits' => is_array($prev) ? ($prev['credits'] ?? null) : null,
            ];
        }

        if (! $this->isMultiple()) {
            $set($path, $items[0] ?? null);

            return;
        }

        $set($path, $items);
    }

    /**
     * @return list<string>
     */
    protected function acceptedMimeTypes(): array
    {
        return match ($this->getAccept()) {
            'images', 'image' => (array) config('vmedia.upload.allowed_image_mimes', []),
            'videos', 'video' => (array) config('vmedia.upload.allowed_video_mimes', []),
            'files', 'file' => (array) config('vmedia.upload.allowed_file_mimes', []),
            default => array_merge(
                (array) config('vmedia.upload.allowed_image_mimes', []),
                (array) config('vmedia.upload.allowed_video_mimes', []),
                (array) config('vmedia.upload.allowed_file_mimes', []),
            ),
        };
    }

    /**
     * @return list<string>
     */
    protected function acceptedExtensions(): array
    {
        $all = (array) config('vmedia.upload.allowed_extensions', []);

        return match ($this->getAccept()) {
            'images', 'image' => array_values(array_intersect($all, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'])),
            'videos', 'video' => array_values(array_intersect($all, ['mp4', 'webm', 'ogg', 'mov', 'm4v'])),
            'files', 'file' => array_values(array_intersect($all, ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'])),
            default => $all,
        };
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function accept(string|Closure $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    public function images(): static
    {
        return $this->accept('images');
    }

    public function videos(): static
    {
        return $this->accept('videos');
    }

    public function files(): static
    {
        return $this->accept('files');
    }

    public function any(): static
    {
        return $this->accept('any');
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function uploadable(bool|Closure $condition = true): static
    {
        $this->uploadEnabled = $condition;

        return $this;
    }

    public function isUploadEnabled(): bool
    {
        return (bool) $this->evaluate($this->uploadEnabled);
    }

    public function attachToRecord(bool|Closure $condition = true): static
    {
        $this->attachToRecord = $condition;

        return $this;
    }

    public function getCollection(): ?string
    {
        $value = $this->evaluate($this->collection);

        return is_string($value) ? $value : null;
    }

    public function getAccept(): string
    {
        $value = $this->evaluate($this->accept);

        return is_string($value) && $value !== '' ? $value : 'images';
    }

    public function shouldAttachToRecord(): bool
    {
        return (bool) $this->evaluate($this->attachToRecord);
    }

    public function vaultGallery(int|Closure|null $galleryId, bool|Closure $lock = true): static
    {
        $this->vaultGalleryId = $galleryId;
        $this->vaultGalleryLocked = $lock;

        return $this;
    }

    public function getVaultGalleryId(): ?int
    {
        $value = $this->evaluate($this->vaultGalleryId);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function isVaultGalleryLocked(): bool
    {
        return (bool) $this->evaluate($this->vaultGalleryLocked);
    }

    protected function resolveUploadGallery(?Get $get = null): ?MediaGallery
    {
        if ($get !== null && ! $this->isVaultGalleryLocked()) {
            return GalleryUploadTarget::resolveFromBrowse(
                is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null,
                is_numeric($get('gallery_id')) ? (int) $get('gallery_id') : null,
                $this->getVaultGalleryId(),
            );
        }

        $galleryId = $this->getVaultGalleryId();

        if ($galleryId === null) {
            return null;
        }

        return MediaGallery::query()->find($galleryId);
    }

    /**
     * @return list<array{uuid: string, caption: string|null, alt: string|null, credits: string|null}>
     */
    public function normalizedItems(): array
    {
        $state = $this->getState();

        if (is_string($state) && $state !== '') {
            return [[
                'uuid' => $state,
                'caption' => null,
                'alt' => null,
                'credits' => null,
            ]];
        }

        if (! is_array($state)) {
            return [];
        }

        // Single-item associative shape: ['uuid' => ..., 'caption' => ...]
        if (isset($state['uuid']) && is_string($state['uuid'])) {
            return [[
                'uuid' => $state['uuid'],
                'caption' => filled($state['caption'] ?? null) ? trim((string) $state['caption']) : null,
                'alt' => filled($state['alt'] ?? null) ? trim((string) $state['alt']) : null,
                'credits' => filled($state['credits'] ?? null) ? trim((string) $state['credits']) : null,
            ]];
        }

        $items = [];

        foreach ($state as $row) {
            if (is_string($row) && $row !== '') {
                $items[] = [
                    'uuid' => $row,
                    'caption' => null,
                    'alt' => null,
                    'credits' => null,
                ];

                continue;
            }

            if (! is_array($row) || ! isset($row['uuid']) || ! is_string($row['uuid']) || $row['uuid'] === '') {
                continue;
            }

            $items[] = [
                'uuid' => $row['uuid'],
                'caption' => filled($row['caption'] ?? null) ? trim((string) $row['caption']) : null,
                'alt' => filled($row['alt'] ?? null) ? trim((string) $row['alt']) : null,
                'credits' => filled($row['credits'] ?? null) ? trim((string) $row['credits']) : null,
            ];
        }

        return array_values($items);
    }

    /**
     * @return list<string>
     */
    public function normalizedUuids(): array
    {
        return array_values(array_column($this->normalizedItems(), 'uuid'));
    }

    /**
     * @return list<array{uuid: string, name: string, thumb: string|null, src: string, type: string}>
     */
    public function browserAssets(?int $galleryId = null, ?string $search = null, int $page = 1, int $perPage = 20): array
    {
        return $this->browserPage($galleryId, $search, $page, $perPage)['assets'];
    }

    /**
     * @return array{
     *   assets: list<array{uuid: string, name: string, thumb: string|null, src: string, type: string}>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     * }
     */
    public function browserPage(
        ?int $galleryId = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 20,
        ?int $parentFolderId = null,
    ): array {
        $type = match ($this->getAccept()) {
            'images', 'image' => 'image',
            'videos', 'video' => 'video',
            'files', 'file' => 'file',
            default => null,
        };

        $result = GalleryBrowser::paginateForPicker($type, $parentFolderId, $galleryId, $search, max(1, $page), max(1, $perPage));

        return [
            'assets' => array_map(
                static fn (array $asset): array => MediaLibrary::toBrowserTile($asset),
                $result['data'],
            ),
            'meta' => $result['meta'],
        ];
    }

    /**
     * @return list<array{uuid: string, name: string, thumb: string|null, src: string, type: string, caption: string|null, alt: string|null, credits: string|null, has_override: bool}>
     */
    public function selectedPayload(): array
    {
        $items = $this->normalizedItems();

        if ($items === []) {
            return [];
        }

        $uuids = array_column($items, 'uuid');
        $metaByUuid = collect($items)->keyBy('uuid');

        return MediaItem::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->sortBy(fn (MediaItem $media): int => array_search((string) $media->uuid, $uuids, true) ?: 0)
            ->map(function (MediaItem $media) use ($metaByUuid): array {
                $meta = $metaByUuid->get((string) $media->uuid, []);
                $caption = is_array($meta) ? ($meta['caption'] ?? null) : null;
                $alt = is_array($meta) ? ($meta['alt'] ?? null) : null;
                $credits = is_array($meta) ? ($meta['credits'] ?? null) : null;

                $icon = FileTypeIcon::forMedia($media);

                return [
                    'uuid' => (string) $media->uuid,
                    'name' => $media->displayTitle(),
                    'thumb' => MediaLibrary::thumbUrl($media),
                    'src' => MediaLibrary::publicUrl($media),
                    'type' => MediaLibrary::assetType($media),
                    'icon' => $icon['icon'],
                    'icon_label' => $icon['icon_label'],
                    'caption' => $caption,
                    'alt' => $alt,
                    'credits' => $credits,
                    'has_override' => filled($caption) || filled($alt) || filled($credits),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function pickerOptions(?int $galleryId = null): array
    {
        $options = [];

        foreach ($this->browserAssets($galleryId) as $asset) {
            $options[$asset['uuid']] = $asset['name'].' ('.$asset['type'].')';
        }

        return $options;
    }

    /**
     * @param  list<string>  $uuids
     * @return array<string, string>
     */
    public function labelsForUuids(array $uuids): array
    {
        return MediaItem::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->mapWithKeys(fn (MediaItem $media): array => [
                (string) $media->uuid => $media->displayTitle(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $record = $this->getRecord();

        return [
            'selected' => $this->selectedPayload(),
            'stateItems' => $this->normalizedItems(),
            'isMultiple' => $this->isMultiple(),
            'editActionKey' => $this->getKey(),
            'recordKey' => $record?->getKey() ?? 'new',
        ];
    }

    /**
     * @phpstan-assert-if-true Model&HasAttachedMedia $record
     */
    protected static function usesAttachedMedia(Model $record): bool
    {
        return in_array(HasAttachedMedia::class, class_uses_recursive($record), true);
    }
}
