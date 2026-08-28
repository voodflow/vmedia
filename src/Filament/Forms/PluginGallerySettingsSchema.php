<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Voodflow\Vmedia\Models\MediaGallery;

final class PluginGallerySettingsSchema
{
    /**
     * @return list<Component>
     */
    public static function section(
        string $statePath = 'media',
        string $groupLabelKey = 'vmedia::admin.integration.group_label',
        string $albumLabelKey = 'vmedia::admin.integration.album_label',
    ): array {
        return [
            Section::make(__('vmedia::admin.integration.section'))
                ->description(__('vmedia::admin.integration.section_help'))
                ->schema([
                    Toggle::make($statePath.'.enabled')
                        ->label(__('vmedia::admin.integration.enabled'))
                        ->default(true),
                    Select::make($statePath.'.root_parent_id')
                        ->label(__('vmedia::admin.integration.root_parent'))
                        ->options(fn (): array => MediaGallery::query()
                            ->where('kind', MediaGallery::KIND_GROUP)
                            ->orderBy('sort_order')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->helperText(__('vmedia::admin.integration.root_parent_help')),
                    Toggle::make($statePath.'.group.enabled')
                        ->label(__('vmedia::admin.integration.auto_group'))
                        ->default(true),
                    TextInput::make($statePath.'.group.name')
                        ->label(__($groupLabelKey))
                        ->default('{event.title}')
                        ->maxLength(255),
                    TextInput::make($statePath.'.group.slug')
                        ->label(__('vmedia::admin.integration.group_slug'))
                        ->default('{event.slug}')
                        ->maxLength(120)
                        ->helperText(__('vmedia::admin.integration.template_help')),
                    Toggle::make($statePath.'.album.enabled')
                        ->label(__('vmedia::admin.integration.auto_album'))
                        ->default(true),
                    TextInput::make($statePath.'.album.name')
                        ->label(__($albumLabelKey))
                        ->default('{entity.name}')
                        ->maxLength(255),
                    TextInput::make($statePath.'.album.slug')
                        ->label(__('vmedia::admin.integration.album_slug'))
                        ->default('{entity.slug}')
                        ->maxLength(120)
                        ->helperText(__('vmedia::admin.integration.template_help')),
                ])
                ->columns(2),
        ];
    }
}
