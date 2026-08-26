<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\Vmedia\Filament\Resources\MediaItemResource\Pages\ManageMediaItems;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Models\MediaVault;
use Voodflow\Vmedia\Support\FileTypeIcon;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\MediaUsage;
use Voodflow\Vmedia\Support\UploadGuard;
use Voodflow\Vmedia\Support\ZipImporter;

/**
 * Flat library of vault media with gallery memberships (no per-row reassignment).
 */
class MediaItemResource extends Resource
{
    protected static ?string $model = MediaItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $slug = 'vmedia/library';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('vmedia.navigation.group', 'Media');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('vmedia.navigation.sort', 40);
    }

    public static function getNavigationLabel(): string
    {
        return __('vmedia::admin.library.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('vmedia::admin.library.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vmedia::admin.library.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['galleries:id,name'])
            ->where('model_type', (new MediaVault)->getMorphClass())
            ->whereIn('collection_name', [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
                MediaGallery::COLLECTION_FILES,
            ]);
    }

    public static function table(Table $table): Table
    {
        $disk = (string) config('vmedia.disk', 'public');

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('preview')
                    ->label(__('vmedia::admin.library.preview'))
                    ->disk($disk)
                    ->height(48)
                    ->width(48)
                    ->square()
                    ->visibility('public')
                    ->state(function (MediaItem $record): ?string {
                        $thumb = MediaLibrary::thumbUrl($record);

                        if ($thumb === null) {
                            return null;
                        }

                        return ltrim(str_replace('/storage/', '', $thumb), '/');
                    })
                    ->defaultImageUrl(fn (MediaItem $record): string => FileTypeIcon::dataUriFor($record)),
                TextColumn::make('name')
                    ->label(__('vmedia::admin.library.name'))
                    ->searchable()
                    ->sortable()
                    ->description(function (MediaItem $record): string {
                        $parts = [(string) $record->file_name];
                        $caption = $record->caption();
                        $alt = $record->alt();
                        $icon = FileTypeIcon::forMedia($record);
                        $parts[] = $icon['icon_label'];

                        if ($caption !== null) {
                            $parts[] = $caption;
                        }

                        if ($alt !== null) {
                            $parts[] = 'alt: '.$alt;
                        }

                        return implode(' — ', $parts);
                    }),
                TextColumn::make('galleries.name')
                    ->label(__('vmedia::admin.library.galleries'))
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('usage')
                    ->label(__('vmedia::admin.library.usage'))
                    ->state(fn (MediaItem $record): string => (string) MediaUsage::attachmentCount($record))
                    ->badge()
                    ->color(fn (string $state): string => ((int) $state) > 0 ? 'warning' : 'gray')
                    ->tooltip(__('vmedia::admin.library.usage_help')),
                IconColumn::make('kind')
                    ->label(__('vmedia::admin.library.type'))
                    ->state(fn (MediaItem $record): string => MediaLibrary::assetType($record))
                    ->icon(fn (string $state): string => match ($state) {
                        'video' => 'heroicon-o-film',
                        'file' => 'heroicon-o-document',
                        default => 'heroicon-o-photo',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'video' => 'warning',
                        'file' => 'gray',
                        default => 'success',
                    })
                    ->tooltip(fn (MediaItem $record): string => $record->kindLabel())
                    ->alignCenter(),
                TextColumn::make('human_readable_size')
                    ->label(__('vmedia::admin.library.size'))
                    ->state(fn (MediaItem $record): string => $record->human_readable_size)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('vmedia::admin.library.uploaded_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('deleted_at')
                    ->label(__('vmedia::admin.library.trashed_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gallery_id')
                    ->label(__('vmedia::admin.library.gallery'))
                    ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (filled($value)) {
                            $query->whereHas('galleries', fn (Builder $builder): Builder => $builder->whereKey($value));
                        }

                        return $query;
                    }),
                SelectFilter::make('collection_name')
                    ->label(__('vmedia::admin.library.type'))
                    ->options([
                        MediaGallery::COLLECTION_IMAGES => __('vmedia::admin.library.photos'),
                        MediaGallery::COLLECTION_VIDEOS => __('vmedia::admin.library.videos'),
                        MediaGallery::COLLECTION_FILES => __('vmedia::admin.library.documents'),
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::editDetailsAction(),
                    Action::make('open')
                        ->label(__('vmedia::admin.library.open'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (MediaItem $record): string => MediaLibrary::publicUrl($record))
                        ->openUrlInNewTab(),
                    DeleteAction::make()
                        ->successNotificationTitle(__('vmedia::admin.library.deleted'))
                        ->using(function (MediaItem $record): void {
                            try {
                                MediaLibrary::delete($record, force: false);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title($exception->getMessage())
                                    ->body(collect($exception->errors())->flatten()->implode(' '))
                                    ->send();

                                throw $exception;
                            }
                        }),
                    RestoreAction::make()
                        ->using(fn (MediaItem $record) => MediaLibrary::restore($record)),
                    ForceDeleteAction::make()
                        ->using(function (MediaItem $record): void {
                            MediaLibrary::delete($record, force: true);
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip(__('vmedia::admin.galleries.media.actions')),
            ])
            ->recordActionsColumnLabel(null)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('editMeta')
                        ->label(__('vmedia::admin.library.bulk_edit_meta'))
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Repeater::make('items')
                                ->label(__('vmedia::admin.library.refine_items'))
                                ->schema(static::metaFieldsSchema())
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(2)
                                ->columnSpanFull(),
                        ])
                        ->fillForm(fn (Collection $records): array => [
                            'items' => $records->map(fn (MediaItem $record): array => static::metaFormState($record))->values()->all(),
                        ])
                        ->action(function (array $data): void {
                            static::persistMetaItems($data['items'] ?? []);

                            Notification::make()
                                ->success()
                                ->title(__('vmedia::admin.library.meta_saved'))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assignGalleries')
                        ->label(__('vmedia::admin.library.bulk_assign'))
                        ->icon('heroicon-o-folder-open')
                        ->schema([
                            Select::make('gallery_ids')
                                ->label(__('vmedia::admin.library.galleries'))
                                ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                                ->multiple()
                                ->required()
                                ->searchable(),
                            Toggle::make('replace')
                                ->label(__('vmedia::admin.library.bulk_replace'))
                                ->helperText(__('vmedia::admin.library.bulk_replace_help'))
                                ->default(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            MediaLibrary::assignGalleries(
                                $records,
                                $data['gallery_ids'] ?? [],
                                (bool) ($data['replace'] ?? false),
                            );

                            Notification::make()
                                ->success()
                                ->title(__('vmedia::admin.library.bulk_assign_done'))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('createGallery')
                        ->label(__('vmedia::admin.library.bulk_create_gallery'))
                        ->icon('heroicon-o-plus-circle')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('vmedia::admin.galleries.fields.name'))
                                ->required()
                                ->maxLength(120),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $gallery = MediaLibrary::createGalleryWithMedia(
                                (string) $data['name'],
                                $records,
                            );

                            Notification::make()
                                ->success()
                                ->title(__('vmedia::admin.library.bulk_create_gallery_done', [
                                    'name' => $gallery->name,
                                ]))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            foreach ($records as $record) {
                                if (! $record instanceof MediaItem) {
                                    continue;
                                }

                                try {
                                    MediaLibrary::delete($record, force: false);
                                } catch (ValidationException) {
                                    // Skip protected rows; UI still completes for the rest.
                                }
                            }
                        }),
                    RestoreBulkAction::make()
                        ->using(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof MediaItem) {
                                    MediaLibrary::restore($record);
                                }
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof MediaItem) {
                                    MediaLibrary::delete($record, force: true);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading(__('vmedia::admin.library.empty_heading'))
            ->emptyStateDescription(__('vmedia::admin.library.empty_body'))
            ->emptyStateActions([
                static::uploadAction(),
            ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function metaFieldsSchema(): array
    {
        return [
            Hidden::make('id')->required(),
            TextInput::make('name')
                ->label(__('vmedia::admin.library.name'))
                ->required()
                ->maxLength(255)
                ->helperText(__('vmedia::admin.library.name_help')),
            TextInput::make('alt')
                ->label(__('vmedia::admin.library.alt'))
                ->maxLength(255)
                ->helperText(__('vmedia::admin.library.alt_help'))
                ->visible(fn (?MediaItem $record, Get $get): bool => static::metaRecordIsImage($record, $get)),
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
                ->visible(fn (?MediaItem $record, Get $get): bool => static::metaRecordIsVideo($record, $get))
                ->options(fn (): array => MediaItem::query()
                    ->where('collection_name', MediaGallery::COLLECTION_IMAGES)
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get()
                    ->mapWithKeys(fn (MediaItem $media): array => [
                        (string) $media->uuid => $media->displayTitle(),
                    ])
                    ->all()),
            TextInput::make('file_name')
                ->label(__('vmedia::admin.library.storage_name'))
                ->disabled()
                ->dehydrated(false),
        ];
    }

    /**
     * @return array{id: int, name: string, caption: string|null, alt: string|null, credits: string|null, poster_uuid: string|null, file_name: string}
     */
    public static function metaFormState(MediaItem $record): array
    {
        return [
            'id' => (int) $record->getKey(),
            'name' => $record->displayTitle(),
            'caption' => $record->caption(),
            'alt' => $record->alt(),
            'credits' => $record->credits(),
            'poster_uuid' => $record->isVideo() ? $record->posterUuid() : null,
            'file_name' => (string) $record->file_name,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function persistMetaItems(array $items): void
    {
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $record = MediaItem::query()->find($id);

            if ($record === null) {
                continue;
            }

            $record->name = trim((string) ($item['name'] ?? $record->displayTitle()));
            $record->setCaption(isset($item['caption']) ? (string) $item['caption'] : null);
            $record->setCredits(isset($item['credits']) ? (string) $item['credits'] : null);

            if ($record->isImage()) {
                $record->setAlt(isset($item['alt']) ? (string) $item['alt'] : null);
            }

            if ($record->isVideo()) {
                $record->setPosterUuid(isset($item['poster_uuid']) ? (string) $item['poster_uuid'] : null);
            }

            $record->save();
        }
    }

    protected static function metaRecordIsVideo(?MediaItem $record, Get $get): bool
    {
        if ($record instanceof MediaItem) {
            return $record->isVideo();
        }

        $id = (int) ($get('id') ?? 0);

        if ($id < 1) {
            return false;
        }

        return MediaItem::query()->find($id)?->isVideo() ?? false;
    }

    protected static function metaRecordIsImage(?MediaItem $record, Get $get): bool
    {
        if ($record instanceof MediaItem) {
            return $record->isImage();
        }

        $id = (int) ($get('id') ?? 0);

        if ($id < 1) {
            return false;
        }

        return MediaItem::query()->find($id)?->isImage() ?? false;
    }

    public static function editDetailsAction(): Action
    {
        return Action::make('editDetails')
            ->label(__('vmedia::admin.library.edit'))
            ->icon('heroicon-o-pencil-square')
            ->fillForm(fn (MediaItem $record): array => static::metaFormState($record))
            ->schema(static::metaFieldsSchema())
            ->action(function (MediaItem $record, array $data): void {
                static::persistMetaItems([[
                    'id' => (int) $record->getKey(),
                    ...$data,
                ]]);

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.library.meta_saved'))
                    ->send();
            });
    }

    public static function refineUploadedAction(): Action
    {
        return Action::make('refineUploaded')
            ->label(__('vmedia::admin.library.refine_title'))
            ->modalHeading(__('vmedia::admin.library.refine_title'))
            ->modalDescription(__('vmedia::admin.library.refine_body'))
            ->modalSubmitActionLabel(__('vmedia::admin.library.refine_save'))
            ->visible(false)
            ->schema([
                Repeater::make('items')
                    ->label(__('vmedia::admin.library.refine_items'))
                    ->schema(static::metaFieldsSchema())
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->fillForm(fn (array $arguments): array => [
                'items' => $arguments['items'] ?? [],
            ])
            ->action(function (array $data): void {
                static::persistMetaItems($data['items'] ?? []);

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.library.meta_saved'))
                    ->send();
            });
    }

    /**
     * @param  array{gallery_ids?: list<int|string>, files?: mixed}  $data
     * @return list<MediaItem>
     */
    public static function storeUploadedFiles(array $data): array
    {
        $galleryIds = array_map('intval', $data['gallery_ids'] ?? []);
        $galleries = MediaGallery::query()->whereIn('id', $galleryIds)->get();
        $files = $data['files'] ?? [];
        $stored = [];

        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                UploadGuard::assertSafeUpload($file);
                $stored[] = MediaLibrary::store($file, $galleries);

                continue;
            }

            if (! is_string($file) || $file === '') {
                continue;
            }

            if (UploadGuard::containsPathTraversal($file)) {
                continue;
            }

            $disk = (string) (config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'));
            $absolute = Storage::disk($disk)->path($file);

            if (! is_file($absolute)) {
                continue;
            }

            $uploaded = new UploadedFile(
                $absolute,
                basename($file),
                mime_content_type($absolute) ?: null,
                null,
                true,
            );

            $stored[] = MediaLibrary::store($uploaded, $galleries);
        }

        return $stored;
    }

    public static function uploadAction(): Action
    {
        $imageMaxKb = (int) config('vmedia.upload.image_max_kb', 8192);
        $videoMaxKb = (int) config('vmedia.upload.video_max_kb', 51200);
        $fileMaxKb = (int) config('vmedia.upload.file_max_kb', 20480);
        $mimes = array_merge(
            (array) config('vmedia.upload.allowed_image_mimes', []),
            (array) config('vmedia.upload.allowed_video_mimes', []),
            (array) config('vmedia.upload.allowed_file_mimes', []),
        );
        $extensions = (array) config('vmedia.upload.allowed_extensions', []);

        return Action::make('upload')
            ->label(__('vmedia::admin.library.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                Select::make('gallery_ids')
                    ->label(__('vmedia::admin.library.galleries'))
                    ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->default(fn (): array => [(int) MediaGallery::default()->getKey()])
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->helperText(__('vmedia::admin.library.upload_galleries_help')),
                FileUpload::make('files')
                    ->label(__('vmedia::admin.library.files'))
                    ->multiple()
                    ->required()
                    ->storeFiles(false)
                    ->imagePreviewHeight('120')
                    ->acceptedFileTypes($mimes)
                    ->rules([
                        File::types($extensions)->max(max($imageMaxKb, $videoMaxKb, $fileMaxKb)),
                    ])
                    ->helperText(__('vmedia::admin.library.upload_help')),
            ])
            ->action(function (array $data, Action $action): void {
                $stored = static::storeUploadedFiles($data);

                if ($stored === []) {
                    return;
                }

                $livewire = $action->getLivewire();

                if ($livewire instanceof Component && method_exists($livewire, 'mountAction')) {
                    $livewire->mountAction('refineUploaded', [
                        'items' => array_map(
                            static fn (MediaItem $media): array => static::metaFormState($media),
                            $stored,
                        ),
                    ]);
                }
            })
            ->successNotificationTitle(__('vmedia::admin.library.uploaded'));
    }

    public static function importZipAction(): Action
    {
        return Action::make('importZip')
            ->label(__('vmedia::admin.library.import_zip'))
            ->icon('heroicon-o-archive-box-arrow-down')
            ->schema([
                Select::make('gallery_ids')
                    ->label(__('vmedia::admin.library.galleries'))
                    ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->default(fn (): array => [(int) MediaGallery::default()->getKey()])
                    ->multiple()
                    ->required()
                    ->searchable(),
                FileUpload::make('zip')
                    ->label(__('vmedia::admin.library.zip_file'))
                    ->required()
                    ->storeFiles(false)
                    ->acceptedFileTypes([
                        'application/zip',
                        'application/x-zip-compressed',
                    ])
                    ->helperText(__('vmedia::admin.library.import_zip_help')),
            ])
            ->action(function (array $data, Action $action): void {
                $galleryIds = array_map('intval', $data['gallery_ids'] ?? []);
                $galleries = MediaGallery::query()->whereIn('id', $galleryIds)->get();
                $zip = $data['zip'] ?? null;

                if (is_array($zip)) {
                    $zip = reset($zip);
                }

                if (! $zip instanceof TemporaryUploadedFile) {
                    return;
                }

                $result = ZipImporter::import($zip, $galleries);
                $stored = $result['items'];

                Notification::make()
                    ->success()
                    ->title(__('vmedia::admin.library.import_zip_done', [
                        'count' => $result['imported'],
                        'skipped' => $result['skipped'],
                    ]))
                    ->send();

                if ($stored === []) {
                    return;
                }

                $livewire = $action->getLivewire();

                if ($livewire instanceof Component && method_exists($livewire, 'mountAction')) {
                    $livewire->mountAction('refineUploaded', [
                        'items' => array_map(
                            static fn (MediaItem $media): array => static::metaFormState($media),
                            $stored,
                        ),
                    ]);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMediaItems::route('/'),
        ];
    }
}
