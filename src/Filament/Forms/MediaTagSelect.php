<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaTag;
use Voodflow\Vmedia\Support\GalleryPath;

final class MediaTagSelect
{
    /**
     * @param  callable(Get, ?MediaGallery): list<int>|null  $allowedTagIds
     */
    public static function make(string $name, ?callable $allowedTagIds = null): Select
    {
        return Select::make($name)
            ->multiple()
            ->searchable()
            ->preload()
            ->options(function (Get $get, ?MediaGallery $record) use ($allowedTagIds, $name): array {
                $query = MediaTag::query()->orderBy('sort_order')->orderBy('name');
                $visibleIds = null;

                if ($allowedTagIds !== null) {
                    $visibleIds = $allowedTagIds($get, $record);
                }

                $selectedIds = array_values(array_filter(array_map(
                    fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null,
                    (array) $get($name),
                )));

                if ($record instanceof MediaGallery && $record->exists && $name === 'tag_ids') {
                    $tagKey = (new MediaTag)->getQualifiedKeyName();
                    $selectedIds = array_values(array_unique(array_merge(
                        $selectedIds,
                        $record->tags()->pluck($tagKey)->map(fn ($id): int => (int) $id)->all(),
                    )));
                }

                if ($visibleIds !== null && $visibleIds !== []) {
                    $visibleIds = array_values(array_unique(array_merge($visibleIds, $selectedIds)));
                    $query->whereIn('id', $visibleIds);
                }

                return $query->pluck('name', 'id')->all();
            })
            ->getOptionLabelsUsing(fn (array $values): array => self::resolveTagLabels($values))
            ->createOptionForm([
                TextInput::make('name')
                    ->label(__('vmedia::admin.tags.fields.name'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('type')
                    ->label(__('vmedia::admin.tags.fields.type'))
                    ->maxLength(64)
                    ->placeholder(__('vmedia::admin.tags.helpers.type_placeholder'))
                    ->helperText(__('vmedia::admin.tags.helpers.type')),
            ])
            ->createOptionUsing(function (array $data, Get $get, ?MediaGallery $record) use ($name): int {
                $tag = MediaTag::query()->create([
                    'name' => trim((string) $data['name']),
                    'type' => filled($data['type'] ?? null) ? trim((string) $data['type']) : null,
                ]);

                $tagId = (int) $tag->getKey();

                if ($name === 'tag_ids') {
                    $parentId = $get('parent_id') ?? $record?->parent_id;

                    if ($parentId) {
                        MediaGallery::query()
                            ->find($parentId)
                            ?->allowedTags()
                            ->syncWithoutDetaching([$tagId]);
                    }
                }

                return $tagId;
            });
    }

    /**
     * @param  list<int|string>  $values
     * @return array<string, string>
     */
    public static function resolveTagLabels(array $values): array
    {
        $ids = array_values(array_filter(array_map(
            fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null,
            $values,
        )));

        if ($ids === []) {
            return [];
        }

        return MediaTag::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function parentFolderOptions(?MediaGallery $record = null): array
    {
        return MediaGallery::query()
            ->where('kind', MediaGallery::KIND_GROUP)
            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (MediaGallery $folder): array => [
                (int) $folder->getKey() => self::parentFolderLabel($folder),
            ])
            ->all();
    }

    public static function parentFolderLabel(MediaGallery $folder): string
    {
        $indent = str_repeat('— ', GalleryPath::depth($folder));
        $path = GalleryPath::toPath($folder);

        if (GalleryPath::depth($folder) === 0 || $path === (string) $folder->name) {
            return $indent.$folder->name;
        }

        return $indent.$folder->name.' · '.$path;
    }

    public static function parentFolderSelect(): Select
    {
        return Select::make('parent_id')
            ->label(__('vmedia::admin.galleries.fields.parent'))
            ->options(fn (?MediaGallery $record): array => self::parentFolderOptions($record))
            ->getOptionLabelUsing(function (mixed $value): ?string {
                if (blank($value)) {
                    return null;
                }

                $folder = MediaGallery::query()->find($value);

                return $folder instanceof MediaGallery
                    ? self::parentFolderLabel($folder)
                    : (string) $value;
            })
            ->searchable()
            ->nullable()
            ->helperText(__('vmedia::admin.galleries.helpers.parent'))
            ->createOptionForm([
                TextInput::make('name')
                    ->label(__('vmedia::admin.galleries.fields.name'))
                    ->required()
                    ->maxLength(120),
            ])
            ->createOptionUsing(function (array $data): int {
                $folder = MediaGallery::query()->create([
                    'name' => trim((string) $data['name']),
                    'kind' => MediaGallery::KIND_GROUP,
                    'is_public' => true,
                    'sort_order' => (int) (MediaGallery::query()->max('sort_order') ?? 0) + 1,
                ]);

                return (int) $folder->getKey();
            });
    }
}
