<?php

declare(strict_types=1);

namespace Voodflow\VoodbuilderMedia\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource\Pages\CreateMediaGallery;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource\Pages\EditMediaGallery;
use Voodflow\VoodbuilderMedia\Filament\Resources\MediaGalleryResource\Pages\ListMediaGalleries;
use Voodflow\VoodbuilderMedia\Models\MediaGallery;

class MediaGalleryResource extends Resource
{
    protected static ?string $model = MediaGallery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'voodbuilder-media/galleries';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('voodbuilder-media.navigation.group', 'Media');
    }

    public static function getNavigationLabel(): string
    {
        return __('voodbuilder-media::admin.galleries.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('voodbuilder-media::admin.galleries.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('voodbuilder-media::admin.galleries.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('name')
                        ->label(__('voodbuilder-media::admin.galleries.fields.name'))
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true),
                    TextInput::make('slug')
                        ->label(__('voodbuilder-media::admin.galleries.fields.slug'))
                        ->maxLength(120)
                        ->unique(ignoreRecord: true),
                    Textarea::make('description')
                        ->label(__('voodbuilder-media::admin.galleries.fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('is_default')
                        ->label(__('voodbuilder-media::admin.galleries.fields.is_default'))
                        ->helperText(__('voodbuilder-media::admin.galleries.helpers.is_default')),
                    Toggle::make('is_public')
                        ->label(__('voodbuilder-media::admin.galleries.fields.is_public'))
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label(__('voodbuilder-media::admin.galleries.fields.sort_order'))
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
                    ->label(__('voodbuilder-media::admin.galleries.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (MediaGallery $record): ?string => $record->description),
                TextColumn::make('slug')
                    ->label(__('voodbuilder-media::admin.galleries.fields.slug'))
                    ->toggleable(),
                IconColumn::make('is_default')
                    ->label(__('voodbuilder-media::admin.galleries.fields.is_default'))
                    ->boolean(),
                IconColumn::make('is_public')
                    ->label(__('voodbuilder-media::admin.galleries.fields.is_public'))
                    ->boolean(),
                TextColumn::make('media_count')
                    ->label(__('voodbuilder-media::admin.galleries.fields.media_count'))
                    ->counts('media'),
                TextColumn::make('sort_order')
                    ->label(__('voodbuilder-media::admin.galleries.fields.sort_order'))
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('browse')
                    ->label(__('voodbuilder-media::admin.galleries.browse_media'))
                    ->icon('heroicon-o-photo')
                    ->url(fn (MediaGallery $record): string => MediaItemResource::getUrl('index', [
                        'tableFilters' => [
                            'gallery_id' => ['value' => $record->getKey()],
                        ],
                    ])),
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
