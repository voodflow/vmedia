<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages\CreateMediaGallery;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages\EditMediaGallery;
use Voodflow\Vmedia\Filament\Resources\MediaGalleryResource\Pages\ListMediaGalleries;
use Voodflow\Vmedia\Models\MediaGallery;

class MediaGalleryResource extends Resource
{
    protected static ?string $model = MediaGallery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $slug = 'vmedia/galleries';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('vmedia.navigation.group', 'Media');
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

        // Filament 5 ListRecords binds filters via #[Url(as: 'filters')], not tableFilters.
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
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(__('vmedia::admin.galleries.helpers.slug_locked'))
                        ->visibleOn('edit'),
                    Textarea::make('description')
                        ->label(__('vmedia::admin.galleries.fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('is_default')
                        ->label(__('vmedia::admin.galleries.fields.is_default'))
                        ->helperText(__('vmedia::admin.galleries.helpers.is_default')),
                    Toggle::make('is_public')
                        ->label(__('vmedia::admin.galleries.fields.is_public'))
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label(__('vmedia::admin.galleries.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('vmedia::admin.galleries.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (MediaGallery $record): ?string => $record->description),
                TextColumn::make('slug')
                    ->label(__('vmedia::admin.galleries.fields.slug'))
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
                TextColumn::make('sort_order')
                    ->label(__('vmedia::admin.galleries.fields.sort_order'))
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('browse')
                    ->label(__('vmedia::admin.galleries.browse_media'))
                    ->icon('heroicon-o-photo')
                    ->url(fn (MediaGallery $record): string => static::libraryUrlForGallery($record)),
                DeleteAction::make()
                    ->disabled(fn (MediaGallery $record): bool => $record->is_default),
            ])
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
