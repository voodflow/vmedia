<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Voodflow\Vmedia\Filament\Forms\MediaTagSelect;
use Voodflow\Vmedia\Models\MediaGallery;

/**
 * Shared folder / album filters for the vault media browser (picker modals).
 */
final class GalleryBrowser
{
    /**
     * @return array<int, Select|TextInput>
     */
    public static function filterFields(bool $lockedGallery = false): array
    {
        if ($lockedGallery) {
            return [
                TextInput::make('q')
                    ->label(__('vmedia::admin.picker.search'))
                    ->placeholder(__('vmedia::admin.picker.search_placeholder'))
                    ->live(debounce: 300)
                    ->nullable()
                    ->afterStateUpdated(fn (Set $set) => $set('browser_page', 1)),
            ];
        }

        return [
            Select::make('parent_folder_id')
                ->label(__('vmedia::admin.galleries.fields.parent'))
                ->options(fn (): array => MediaTagSelect::parentFolderOptions())
                ->getOptionLabelUsing(function (mixed $value): ?string {
                    if (blank($value)) {
                        return null;
                    }

                    $folder = MediaGallery::query()->find($value);

                    return $folder instanceof MediaGallery
                        ? MediaTagSelect::parentFolderLabel($folder)
                        : (string) $value;
                })
                ->searchable()
                ->nullable()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('gallery_id', null);
                    $set('browser_page', 1);
                }),
            Select::make('gallery_id')
                ->label(__('vmedia::admin.library.gallery'))
                ->options(fn (Get $get): array => self::albumOptions(
                    is_numeric($get('parent_folder_id')) ? (int) $get('parent_folder_id') : null,
                ))
                ->placeholder(fn (Get $get): ?string => filled($get('parent_folder_id'))
                    ? __('vmedia::admin.picker.all_albums_in_folder')
                    : __('vmedia::admin.picker.all_galleries'))
                ->searchable()
                ->nullable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('browser_page', 1)),
            TextInput::make('q')
                ->label(__('vmedia::admin.picker.search'))
                ->placeholder(__('vmedia::admin.picker.search_placeholder'))
                ->live(debounce: 300)
                ->nullable()
                ->afterStateUpdated(fn (Set $set) => $set('browser_page', 1)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function albumOptions(?int $parentFolderId = null): array
    {
        return MediaGallery::query()
            ->where('kind', MediaGallery::KIND_ALBUM)
            ->when(
                filled($parentFolderId),
                fn (Builder $query) => $query->where('parent_id', $parentFolderId),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (MediaGallery $album): array => [
                (int) $album->getKey() => self::albumLabel($album),
            ])
            ->all();
    }

    public static function albumLabel(MediaGallery $album): string
    {
        return GalleryDisplay::navLabel($album);
    }

    /**
     * @return array{gallery_id: ?int, include_descendants: bool}
     */
    public static function resolveBrowseTarget(?int $parentFolderId, ?int $galleryId): array
    {
        if (is_numeric($galleryId) && (int) $galleryId > 0) {
            return [
                'gallery_id' => (int) $galleryId,
                'include_descendants' => false,
            ];
        }

        if (is_numeric($parentFolderId) && (int) $parentFolderId > 0) {
            return [
                'gallery_id' => (int) $parentFolderId,
                'include_descendants' => true,
            ];
        }

        return [
            'gallery_id' => null,
            'include_descendants' => false,
        ];
    }

    /**
     * @return array{
     *   data: list<array<string, mixed>>,
     *   meta: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     * }
     */
    public static function paginateForPicker(
        ?string $type,
        ?int $parentFolderId,
        ?int $galleryId,
        ?string $search,
        int $page,
        int $perPage,
    ): array {
        $target = self::resolveBrowseTarget($parentFolderId, $galleryId);

        return MediaLibrary::paginateAssets(
            $type,
            $target['gallery_id'],
            $search,
            $page,
            $perPage,
            $target['include_descendants'],
        );
    }

    /**
     * @return array{parent_folder_id: int|null, gallery_id: int|null}
     */
    public static function defaultPickerFilters(?int $galleryId = null): array
    {
        if ($galleryId !== null && $galleryId > 0) {
            $gallery = MediaGallery::query()->find($galleryId);

            return [
                'parent_folder_id' => $gallery?->parent_id !== null ? (int) $gallery->parent_id : null,
                'gallery_id' => $galleryId,
            ];
        }

        $default = MediaGallery::default();

        return [
            'parent_folder_id' => $default->parent_id !== null ? (int) $default->parent_id : null,
            'gallery_id' => (int) $default->getKey(),
        ];
    }
}
