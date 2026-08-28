<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Voodflow\Vmedia\Filament\Resources\MediaTagResource\Pages\ManageMediaTags;
use Voodflow\Vmedia\Models\MediaTag;

class MediaTagResource extends Resource
{
    protected static ?string $model = MediaTag::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $slug = 'vmedia/tags';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('vmedia.navigation.group', 'Media');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('vmedia.navigation.sort', 40) + 2;
    }

    public static function getNavigationLabel(): string
    {
        return __('vmedia::admin.tags.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('vmedia::admin.tags.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vmedia::admin.tags.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('vmedia::admin.tags.fields.name'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('slug')
                    ->label(__('vmedia::admin.tags.fields.slug'))
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                TextInput::make('type')
                    ->label(__('vmedia::admin.tags.fields.type'))
                    ->maxLength(64)
                    ->placeholder(__('vmedia::admin.tags.helpers.type_placeholder'))
                    ->helperText(__('vmedia::admin.tags.helpers.type')),
                TextInput::make('sort_order')
                    ->label(__('vmedia::admin.tags.fields.sort_order'))
                    ->numeric()
                    ->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('vmedia::admin.tags.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('vmedia::admin.tags.fields.slug'))
                    ->toggleable(),
                TextColumn::make('type')
                    ->label(__('vmedia::admin.tags.fields.type'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->label(__('vmedia::admin.tags.fields.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                Filter::make('type')
                    ->label(__('vmedia::admin.tags.fields.type'))
                    ->schema([
                        TextInput::make('type')
                            ->label(__('vmedia::admin.tags.fields.type')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $type = trim((string) ($data['type'] ?? ''));

                        if ($type === '') {
                            return $query;
                        }

                        return $query->where('type', 'like', '%'.$type.'%');
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMediaTags::route('/'),
        ];
    }
}
