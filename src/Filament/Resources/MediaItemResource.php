<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaItemResource\Pages\ManageMediaItems;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;
use Voodflow\VoodbuilderMedia\Support\MediaLibrary;

/**
 * Flat library of all Spatie media across galleries (upload / delete / preview).
 */
class MediaItemResource extends Resource
{
    protected static ?string $model = Media::class;

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
            ->where('model_type', (new MediaGallery)->getMorphClass())
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
                    ->state(function (Media $record) use ($disk): ?string {
                        if (str_starts_with((string) $record->mime_type, 'video/')) {
                            return null;
                        }

                        // Path relative to the public disk — Filament builds /storage/... itself.
                        return (string) $record->getPathRelativeToRoot();
                    }),
                TextColumn::make('name')
                    ->label(__('voodbuilder-media::admin.library.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Media $record): string => (string) $record->file_name),
                TextColumn::make('model_id')
                    ->label(__('voodbuilder-media::admin.library.gallery'))
                    ->formatStateUsing(function (Media $record): string {
                        $gallery = MediaGallery::query()->find($record->model_id);

                        return $gallery?->name ?? '#'.$record->model_id;
                    }),
                TextColumn::make('collection_name')
                    ->label(__('voodbuilder-media::admin.library.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === MediaGallery::COLLECTION_VIDEOS
                        ? __('voodbuilder-media::admin.library.videos')
                        : __('voodbuilder-media::admin.library.images'))
                    ->color(fn (string $state): string => $state === MediaGallery::COLLECTION_VIDEOS ? 'warning' : 'success'),
                TextColumn::make('human_readable_size')
                    ->label(__('voodbuilder-media::admin.library.size'))
                    ->state(fn (Media $record): string => $record->human_readable_size),
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
                            $query->where('model_id', $value);
                        }

                        return $query;
                    }),
                SelectFilter::make('collection_name')
                    ->label(__('voodbuilder-media::admin.library.type'))
                    ->options([
                        MediaGallery::COLLECTION_IMAGES => __('voodbuilder-media::admin.library.images'),
                        MediaGallery::COLLECTION_VIDEOS => __('voodbuilder-media::admin.library.videos'),
                    ]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('voodbuilder-media::admin.library.open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Media $record): string => MediaLibrary::publicUrl($record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->successNotificationTitle(__('voodbuilder-media::admin.library.deleted')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
                Select::make('gallery_id')
                    ->label(__('voodbuilder-media::admin.library.gallery'))
                    ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->default(fn () => MediaGallery::default()->getKey())
                    ->required()
                    ->searchable(),
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
                $gallery = MediaGallery::query()->find($data['gallery_id'] ?? null) ?? MediaGallery::default();
                $files = $data['files'] ?? [];

                if (! is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if ($file instanceof TemporaryUploadedFile) {
                        MediaLibrary::store($file, $gallery);

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

                    $gallery
                        ->addMedia($absolute)
                        ->usingName(pathinfo($file, PATHINFO_FILENAME) ?: basename($file))
                        ->toMediaCollection(
                            str_starts_with((string) mime_content_type($absolute), 'video/')
                                ? MediaGallery::COLLECTION_VIDEOS
                                : MediaGallery::COLLECTION_IMAGES,
                        );
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
