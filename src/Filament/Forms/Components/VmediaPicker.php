<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;

/**
 * Pick existing vault media (single or multiple) into a HasAttachedMedia collection,
 * or dehydrate UUIDs when used as a standalone field.
 */
class VmediaPicker extends Field
{
    protected string $view = 'vmedia::forms.components.vmedia-picker';

    protected string|Closure|null $collection = 'default';

    /**
     * images | videos | files | any
     */
    protected string|Closure $accept = 'images';

    protected bool|Closure $attachToRecord = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (): array|string|null => $this->isMultiple() ? [] : null);

        $this->loadStateFromRelationshipsUsing(static function (VmediaPicker $component, ?Model $record): void {
            if ($record === null || ! $component->shouldAttachToRecord() || ! self::usesAttachedMedia($record)) {
                return;
            }

            $uuids = $record->load('media')
                ->getMedia($component->getCollection() ?? 'default')
                ->when(
                    ! $component->isMultiple(),
                    fn ($items) => $items->take(1),
                )
                ->map(fn (MediaItem $media): string => (string) $media->uuid)
                ->values()
                ->all();

            $component->state($component->isMultiple() ? $uuids : ($uuids[0] ?? null));
        });

        $this->saveRelationshipsUsing(static function (VmediaPicker $component, ?Model $record): void {
            if ($record === null || ! $component->shouldAttachToRecord() || ! self::usesAttachedMedia($record)) {
                return;
            }

            $uuids = $component->normalizedUuids();
            $ids = MediaItem::query()
                ->whereIn('uuid', $uuids)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $record->syncMediaCollection($component->getCollection() ?? 'default', $ids);
        });

        $this->dehydrated(fn (VmediaPicker $component): bool => ! $component->shouldAttachToRecord());

        $this->suffixAction(
            Action::make('browseLibrary')
                ->label(__('vmedia::admin.picker.browse'))
                ->icon('heroicon-o-photo')
                ->modalHeading(__('vmedia::admin.picker.modal_heading'))
                ->modalSubmitActionLabel(__('vmedia::admin.picker.select'))
                ->schema(fn (VmediaPicker $component): array => [
                    Select::make('gallery_id')
                        ->label(__('vmedia::admin.library.gallery'))
                        ->options(fn (): array => MediaGallery::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable(),
                    Select::make('media_uuids')
                        ->label(__('vmedia::admin.picker.items'))
                        ->multiple()
                        ->searchable()
                        ->options(fn (?callable $get) => $component->pickerOptions(
                            is_numeric($get('gallery_id') ?? null) ? (int) $get('gallery_id') : null,
                        ))
                        ->getOptionLabelsUsing(fn (array $values): array => $component->labelsForUuids($values))
                        ->required(),
                ])
                ->fillForm(fn (VmediaPicker $component): array => [
                    'gallery_id' => null,
                    'media_uuids' => $component->normalizedUuids(),
                ])
                ->action(function (array $data, Set $set, VmediaPicker $component): void {
                    $uuids = array_values(array_filter(array_map('strval', $data['media_uuids'] ?? [])));

                    if (! $component->isMultiple()) {
                        $uuids = array_slice($uuids, 0, 1);
                        $set($component->getStatePath(isAbsolute: false), $uuids[0] ?? null);
                    } else {
                        $set($component->getStatePath(isAbsolute: false), $uuids);
                    }

                    Notification::make()
                        ->success()
                        ->title(__('vmedia::admin.picker.selected'))
                        ->send();
                }),
        );
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function accept(string|Closure $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    public function images(): static
    {
        return $this->accept('images');
    }

    public function videos(): static
    {
        return $this->accept('videos');
    }

    public function files(): static
    {
        return $this->accept('files');
    }

    public function any(): static
    {
        return $this->accept('any');
    }

    public function attachToRecord(bool|Closure $condition = true): static
    {
        $this->attachToRecord = $condition;

        return $this;
    }

    public function getCollection(): ?string
    {
        $value = $this->evaluate($this->collection);

        return is_string($value) ? $value : null;
    }

    public function getAccept(): string
    {
        $value = $this->evaluate($this->accept);

        return is_string($value) && $value !== '' ? $value : 'images';
    }

    public function shouldAttachToRecord(): bool
    {
        return (bool) $this->evaluate($this->attachToRecord);
    }

    /**
     * @return list<string>
     */
    public function normalizedUuids(): array
    {
        $state = $this->getState();

        if (is_string($state) && $state !== '') {
            return [$state];
        }

        if (! is_array($state)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $state)));
    }

    /**
     * @return list<array{uuid: string, name: string, thumb: string|null, type: string}>
     */
    public function selectedPayload(): array
    {
        $uuids = $this->normalizedUuids();

        if ($uuids === []) {
            return [];
        }

        return MediaItem::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->sortBy(fn (MediaItem $media): int => array_search((string) $media->uuid, $uuids, true) ?: 0)
            ->map(fn (MediaItem $media): array => [
                'uuid' => (string) $media->uuid,
                'name' => $media->displayTitle(),
                'thumb' => MediaLibrary::thumbUrl($media),
                'type' => MediaLibrary::assetType($media),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function pickerOptions(?int $galleryId = null): array
    {
        $type = match ($this->getAccept()) {
            'images', 'image' => 'image',
            'videos', 'video' => 'video',
            'files', 'file' => 'file',
            default => null,
        };

        $assets = MediaLibrary::paginateAssets($type, $galleryId, null, 1, 96)['data'];

        $options = [];

        foreach ($assets as $asset) {
            $options[$asset['uuid']] = $asset['name'].' ('.$asset['type'].')';
        }

        return $options;
    }

    /**
     * @param  list<string>  $uuids
     * @return array<string, string>
     */
    public function labelsForUuids(array $uuids): array
    {
        return MediaItem::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->mapWithKeys(fn (MediaItem $media): array => [
                (string) $media->uuid => $media->displayTitle(),
            ])
            ->all();
    }

    /**
     * @phpstan-assert-if-true Model&HasAttachedMedia $record
     */
    protected static function usesAttachedMedia(Model $record): bool
    {
        return in_array(HasAttachedMedia::class, class_uses_recursive($record), true);
    }
}
