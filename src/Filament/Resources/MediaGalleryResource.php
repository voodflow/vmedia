<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Voodflow\Vmedia\Filament\Forms\Components\GalleryMediaManager;
use Voodflow\Vmedia\Filament\Forms\MediaTagSelect;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages\CreateMediaGallery;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages\EditMediaGallery;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages\ListMediaGalleries;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\FileTypeIcon;
use Voodflow\Vmedia\Support\GalleryDisplay;
use Voodflow\Vmedia\Support\GalleryPath;
use Voodflow\Vmedia\Support\MediaLibrary;

class MediaGalleryResource extends Resource
{
    protected static ?string $model = MediaGallery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $slug = 'vmedia/galleries';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('vmedia.navigation.group', 'VoodMedia');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('vmedia.navigation.sort', 40) + 1;
    }

    public static function getNavigationLabel(): string
    {
        return __('vmedia::admin.galleries.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('vmedia::admin.galleries.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vmedia::admin.galleries.plural');
    }

    public static function libraryUrlForGallery(MediaGallery|int|string $gallery): string
    {
        $id = $gallery instanceof MediaGallery ? $gallery->getKey() : $gallery;

        return MediaItemResource::getUrl('index', [
            'filters' => [
                'gallery_id' => ['value' => $id],
            ],
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Select::make('kind')
                        ->label(__('vmedia::admin.galleries.fields.kind'))
                        ->options([
                            MediaGallery::KIND_GROUP => __('vmedia::admin.galleries.kinds.group'),
                            MediaGallery::KIND_ALBUM => __('vmedia::admin.galleries.kinds.album'),
                        ])
                        ->default(MediaGallery::KIND_ALBUM)
                        ->required()
                        ->live()
                        ->helperText(__('vmedia::admin.galleries.helpers.kind')),
                    MediaTagSelect::parentFolderSelect(),
                    TextInput::make('name')
                        ->label(__('vmedia::admin.galleries.fields.name'))
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, ?MediaGallery $record): void {
                            if ($record !== null) {
                                return;
                            }

                            $set('slug_preview', filled($state) ? Str::slug($state) : null);
                        }),
                    TextInput::make('slug_preview')
                        ->label(__('vmedia::admin.galleries.fields.slug'))
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder(__('vmedia::admin.galleries.helpers.slug_auto'))
                        ->helperText(__('vmedia::admin.galleries.helpers.slug_auto'))
                        ->visibleOn('create'),
                    TextInput::make('slug')
                        ->label(__('vmedia::admin.galleries.fields.slug'))
                        ->required()
                        ->maxLength(120)
                        ->alphaDash()
                        ->helperText(__('vmedia::admin.galleries.helpers.slug_editable'))
                        ->visibleOn('edit')
                        ->rules(fn (Get $get, ?MediaGallery $record): array => [
                            Rule::unique((new MediaGallery)->getTable(), 'slug')
                                ->where(
                                    'parent_key',
                                    (int) ($get('parent_id') ?? $record?->parent_id ?? 0),
                                )
                                ->ignore($record),
                        ]),
                    Textarea::make('description')
                        ->label(__('vmedia::admin.galleries.fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('is_default')
                        ->label(__('vmedia::admin.galleries.fields.is_default'))
                        ->helperText(__('vmedia::admin.galleries.helpers.is_default'))
                        ->visible(fn (Get $get): bool => $get('kind') === MediaGallery::KIND_ALBUM),
                    Toggle::make('is_public')
                        ->label(__('vmedia::admin.galleries.fields.is_public'))
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label(__('vmedia::admin.galleries.fields.sort_order'))
                        ->numeric()
                        ->default(0)
                        ->dehydrateStateUsing(fn ($state): int => filled($state) ? (int) $state : 0),
                ])
                ->columns(2),
            Section::make(__('vmedia::admin.galleries.tags.allowed_section'))
                ->description(__('vmedia::admin.galleries.tags.allowed_help'))
                ->schema([
                    MediaTagSelect::make('allowed_tag_ids')
                        ->label(__('vmedia::admin.galleries.tags.allowed'))
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => $get('kind') === MediaGallery::KIND_GROUP),
            Section::make(__('vmedia::admin.galleries.tags.section'))
                ->schema([
                    MediaTagSelect::make('tag_ids')
                        ->label(__('vmedia::admin.galleries.tags.label'))
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => $get('kind') === MediaGallery::KIND_ALBUM),
            Section::make(__('vmedia::admin.galleries.media.section'))
                ->description(__('vmedia::admin.galleries.media.section_help'))
                ->schema([
                    GalleryMediaManager::make('gallery_media')
                        ->hiddenLabel()
                        ->dehydrated(true)
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => $get('kind') === MediaGallery::KIND_ALBUM),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return GalleryDisplay::applyTreeOrdering(
            parent::getEloquentQuery()->with(['parent']),
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => GalleryDisplay::applyTreeOrdering($query))
            ->columns([
                TextColumn::make('name')
                    ->label(__('vmedia::admin.galleries.fields.name'))
                    ->searchable(['name', 'slug'])
                    ->sortable(false)
                    ->wrap()
                    ->lineClamp(2)
                    ->extraCellAttributes(['class' => 'max-w-md'])
                    ->formatStateUsing(fn (string $state, MediaGallery $record): string => str_repeat('— ', GalleryPath::depth($record)).GalleryDisplay::navLabel($record))
                    ->description(function (MediaGallery $record): ?string {
                        if (blank($record->description)) {
                            return null;
                        }

                        return Str::limit(str($record->description)->squish()->toString(), 120);
                    })
                    ->tooltip(function (MediaGallery $record): ?string {
                        if (blank($record->description)) {
                            return null;
                        }

                        $description = str($record->description)->squish()->toString();

                        return strlen($description) > 120 ? $description : null;
                    }),
                TextColumn::make('kind')
                    ->label(__('vmedia::admin.galleries.fields.kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === MediaGallery::KIND_GROUP
                        ? __('vmedia::admin.galleries.kinds.group')
                        : __('vmedia::admin.galleries.kinds.album')),
                TextColumn::make('path')
                    ->label(__('vmedia::admin.galleries.fields.path'))
                    ->state(fn (MediaGallery $record): string => GalleryPath::toPath($record))
                    ->toggleable(),
                TextColumn::make('parent.name')
                    ->label(__('vmedia::admin.galleries.fields.parent'))
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_default')
                    ->label(__('vmedia::admin.galleries.fields.is_default'))
                    ->boolean(),
                IconColumn::make('is_public')
                    ->label(__('vmedia::admin.galleries.fields.is_public'))
                    ->boolean(),
                TextColumn::make('media_items_count')
                    ->label(__('vmedia::admin.galleries.fields.media_count'))
                    ->counts('mediaItems'),
                TextColumn::make('children_count')
                    ->label(__('vmedia::admin.galleries.fields.children_count'))
                    ->counts('children')
                    ->tooltip(__('vmedia::admin.galleries.helpers.children_count')),
                TextColumn::make('sort_order')
                    ->label(__('vmedia::admin.galleries.fields.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('vmedia::admin.galleries.fields.kind'))
                    ->options([
                        MediaGallery::KIND_GROUP => __('vmedia::admin.galleries.kinds.group'),
                        MediaGallery::KIND_ALBUM => __('vmedia::admin.galleries.kinds.album'),
                    ]),
                SelectFilter::make('parent_id')
                    ->label(__('vmedia::admin.galleries.fields.parent'))
                    ->options(fn (): array => MediaTagSelect::parentFolderOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('slideshow')
                        ->label(__('vmedia::admin.galleries.media.slideshow'))
                        ->icon('heroicon-o-play')
                        ->visible(fn (MediaGallery $record): bool => $record->isAlbum())
                        ->modalHeading(fn (MediaGallery $record): string => $record->name)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('vmedia::admin.galleries.media.close'))
                        ->modalWidth(Width::FiveExtraLarge)
                        ->modalContent(function (MediaGallery $record) {
                            $slides = $record->mediaItems()
                                ->get()
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

                            return view('vmedia::filament.gallery-slideshow', [
                                'slides' => $slides,
                            ]);
                        }),
                    Action::make('browse')
                        ->label(__('vmedia::admin.galleries.browse_media'))
                        ->icon('heroicon-o-photo')
                        ->visible(fn (MediaGallery $record): bool => $record->isAlbum())
                        ->url(fn (MediaGallery $record): string => static::libraryUrlForGallery($record)),
                    DeleteAction::make()
                        ->disabled(fn (MediaGallery $record): bool => $record->is_default),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip(__('vmedia::admin.galleries.media.actions')),
            ])
            ->recordActionsColumnLabel(null)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaGalleries::route('/'),
            'create' => CreateMediaGallery::route('/create'),
            'edit' => EditMediaGallery::route('/{record}/edit'),
        ];
    }
}
