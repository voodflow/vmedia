<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaItemResource\Pages\ManageMediaItems;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;
use Voodflow\VoodbuilderMedia\Models\MediaItem;
use Voodflow\VoodbuilderMedia\Models\MediaVault;
use Voodflow\VoodbuilderMedia\Support\MediaLibrary;

/**
 * Flat library of vault media with gallery memberships (no per-row reassignment).
 */
class MediaItemResource extends Resource
{
    protected static ?string $model = MediaItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'voodbuilder-media/library';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('voodbuilder-media.navigation.group', 'Media');
    }

    public static function getNavigationLabel(): string
    {
        return __('voodbuilder-media::admin.library.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('voodbuilder-media::admin.library.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('voodbuilder-media::admin.library.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['galleries:id,name'])
            ->where('model_type', (new MediaVault)->getMorphClass())
            ->whereIn('collection_name', [
                MediaGallery::COLLECTION_IMAGES,
                MediaGallery::COLLECTION_VIDEOS,
            ]);
    }

    public static function table(Table $table): Table
    {
        $disk = (string) config('voodbuilder-media.disk', 'public');

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('preview')
                    ->label(__('voodbuilder-media::admin.library.preview'))
                    ->disk($disk)
                    ->height(48)
                    ->width(48)
                    ->square()
                    ->visibility('public')
                    ->state(function (MediaItem $record) use ($disk): ?string {
                        if ($record->isVideo()) {
                            return null;
                        }

                        return (string) $record->getPathRelativeToRoot();
                    }),
                IconColumn::make('is_video')
                    ->label('')
                    ->state(fn (MediaItem $record): bool => $record->isVideo())
                    ->boolean()
                    ->trueIcon('heroicon-o-film')
                    ->falseIcon('heroicon-o-photo')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->alignCenter(),
                TextColumn::make('name')
                    ->label(__('voodbuilder-media::admin.library.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (MediaItem $record): string => (string) $record->file_name),
                TextColumn::make('galleries.name')
                    ->label(__('voodbuilder-media::admin.library.galleries'))
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('collection_name')
                    ->label(__('voodbuilder-media::admin.library.type'))
                    ->badge()
                    ->formatStateUsing(fn (MediaItem $record): string => $record->kindLabel())
                    ->color(fn (MediaItem $record): string => $record->isVideo() ? 'warning' : 'success'),
                TextColumn::make('human_readable_size')
                    ->label(__('voodbuilder-media::admin.library.size'))
                    ->state(fn (MediaItem $record): string => $record->human_readable_size),
                TextColumn::make('created_at')
                    ->label(__('voodbuilder-media::admin.library.uploaded_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('gallery_id')
                    ->label(__('voodbuilder-media::admin.library.gallery'))
                    ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (filled($value)) {
                            $query->whereHas('galleries', fn (Builder $builder): Builder => $builder->whereKey($value));
                        }

                        return $query;
                    }),
                SelectFilter::make('collection_name')
                    ->label(__('voodbuilder-media::admin.library.type'))
                    ->options([
                        MediaGallery::COLLECTION_IMAGES => __('voodbuilder-media::admin.library.photos'),
                        MediaGallery::COLLECTION_VIDEOS => __('voodbuilder-media::admin.library.videos'),
                    ]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('voodbuilder-media::admin.library.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MediaItem $record): string => MediaLibrary::publicUrl($record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->successNotificationTitle(__('voodbuilder-media::admin.library.deleted')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignGalleries')
                        ->label(__('voodbuilder-media::admin.library.bulk_assign'))
                        ->icon('heroicon-o-folder-open')
                        ->schema([
                            Select::make('gallery_ids')
                                ->label(__('voodbuilder-media::admin.library.galleries'))
                                ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                                ->multiple()
                                ->required()
                                ->searchable(),
                            Toggle::make('replace')
                                ->label(__('voodbuilder-media::admin.library.bulk_replace'))
                                ->helperText(__('voodbuilder-media::admin.library.bulk_replace_help'))
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
                                ->title(__('voodbuilder-media::admin.library.bulk_assign_done'))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('createGallery')
                        ->label(__('voodbuilder-media::admin.library.bulk_create_gallery'))
                        ->icon('heroicon-o-plus-circle')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('voodbuilder-media::admin.galleries.fields.name'))
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
                                ->title(__('voodbuilder-media::admin.library.bulk_create_gallery_done', [
                                    'name' => $gallery->name,
                                ]))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('voodbuilder-media::admin.library.empty_heading'))
            ->emptyStateDescription(__('voodbuilder-media::admin.library.empty_body'))
            ->emptyStateActions([
                static::uploadAction(),
            ]);
    }

    public static function uploadAction(): Action
    {
        $imageMaxKb = (int) config('voodbuilder-media.upload.image_max_kb', 8192);
        $videoMaxKb = (int) config('voodbuilder-media.upload.video_max_kb', 51200);

        return Action::make('upload')
            ->label(__('voodbuilder-media::admin.library.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                Select::make('gallery_ids')
                    ->label(__('voodbuilder-media::admin.library.galleries'))
                    ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->default(fn (): array => [(int) MediaGallery::default()->getKey()])
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->helperText(__('voodbuilder-media::admin.library.upload_galleries_help')),
                FileUpload::make('files')
                    ->label(__('voodbuilder-media::admin.library.files'))
                    ->multiple()
                    ->required()
                    ->storeFiles(false)
                    ->imagePreviewHeight('120')
                    ->acceptedFileTypes([
                        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
                        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-m4v',
                    ])
                    ->rules([
                        File::types([
                            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
                            'mp4', 'webm', 'ogg', 'mov', 'm4v',
                        ])->max(max($imageMaxKb, $videoMaxKb)),
                    ])
                    ->helperText(__('voodbuilder-media::admin.library.upload_help')),
            ])
            ->action(function (array $data): void {
                $galleryIds = array_map('intval', $data['gallery_ids'] ?? []);
                $galleries = MediaGallery::query()->whereIn('id', $galleryIds)->get();
                $files = $data['files'] ?? [];

                if (! is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if ($file instanceof TemporaryUploadedFile) {
                        MediaLibrary::store($file, $galleries);

                        continue;
                    }

                    if (! is_string($file) || $file === '') {
                        continue;
                    }

                    $disk = (string) (config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'));
                    $absolute = Storage::disk($disk)->path($file);

                    if (! is_file($absolute)) {
                        continue;
                    }

                    $uploaded = new \Illuminate\Http\UploadedFile(
                        $absolute,
                        basename($file),
                        mime_content_type($absolute) ?: null,
                        null,
                        true,
                    );

                    MediaLibrary::store($uploaded, $galleries);
                }
            })
            ->successNotificationTitle(__('voodbuilder-media::admin.library.uploaded'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMediaItems::route('/'),
        ];
    }
}
