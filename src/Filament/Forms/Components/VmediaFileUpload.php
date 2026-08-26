<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\Vmedia\Concerns\HasAttachedMedia;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;

class VmediaFileUpload extends FileUpload
{
    protected string|Closure|null $collection = null;

    /**
     * @var array<string, mixed>
     */
    public array $imageOptimization = [];

    /**
     * @var array<string, mixed>|Closure|null
     */
    protected array|Closure|null $customProperties = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preventFilePathTampering(false);

        $this->loadStateFromRelationshipsUsing(static function (VmediaFileUpload $component, Model $record): void {
            if (! self::usesAttachedMedia($record)) {
                return;
            }

            $media = $record->load('media')->getMedia($component->getCollection() ?? 'default')
                ->when(
                    ! $component->isMultiple(),
                    fn ($items) => $items->take(1),
                )
                ->mapWithKeys(function (MediaItem $media): array {
                    $uuid = (string) $media->getAttributeValue('uuid');

                    return [$uuid => $uuid];
                })
                ->all();

            $component->rawState($media);
        });

        $this->afterStateHydrated(null);
        $this->beforeStateDehydrated(null);
        $this->dehydrated(false);

        $this->getUploadedFileUsing(static function (VmediaFileUpload $component, string $file): ?array {
            $record = $component->getRecord();

            if ($record === null || ! self::usesAttachedMedia($record)) {
                return null;
            }

            /** @var ?MediaItem $media */
            $media = $record->getRelationValue('media')?->firstWhere('uuid', $file);

            if ($media === null) {
                return null;
            }

            return [
                'name' => $media->displayTitle(),
                'size' => $media->getAttributeValue('size'),
                'type' => $media->getAttributeValue('mime_type'),
                'url' => Str::sanitizeUrl(MediaLibrary::publicUrl($media)),
            ];
        });

        $this->saveRelationshipsUsing(static function (VmediaFileUpload $component): void {
            $component->deleteAbandonedFiles();
            $component->saveUploadedFiles();
        });

        $this->saveUploadedFileUsing(static function (VmediaFileUpload $component, TemporaryUploadedFile $file, ?Model $record): ?string {
            if ($record === null || ! self::usesAttachedMedia($record)) {
                return null;
            }

            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence) {
                return null;
            }

            $stored = MediaLibrary::store(
                $file,
                customProperties: $component->getCustomProperties($file),
            );

            $record->attachMediaItem(
                $component->getCollection() ?? 'default',
                $stored,
                replace: ! $component->isMultiple(),
            );

            return (string) $stored->uuid;
        });

        $this->reorderUploadedFilesUsing(static function (VmediaFileUpload $component, ?Model $record, array $rawState): array {
            if ($record === null || ! self::usesAttachedMedia($record)) {
                return $rawState;
            }

            $uuids = array_values(array_filter(array_keys($rawState)));
            $record->reorderMediaCollection($component->getCollection() ?? 'default', $uuids);

            return $rawState;
        });
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure|null  $properties
     */
    public function customProperties(array|Closure|null $properties): static
    {
        $this->customProperties = $properties;

        return $this;
    }

    public function deleteAbandonedFiles(): void
    {
        $record = $this->getRecord();

        if ($record === null || ! self::usesAttachedMedia($record)) {
            return;
        }

        $record->detachAbandonedMedia(
            $this->getCollection() ?? 'default',
            array_keys($this->getRawState() ?? []),
        );
    }

    public function getCollection(): ?string
    {
        return $this->evaluate($this->collection);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomProperties(TemporaryUploadedFile|UploadedFile $file): array
    {
        return $this->evaluate($this->customProperties, [
            'file' => $file,
        ]) ?? [];
    }

    public function getDiskName(): string
    {
        $name = $this->evaluate($this->diskName);

        if (filled($name)) {
            return (string) $name;
        }

        return (string) config('vmedia.disk', 'public');
    }

    /**
     * @phpstan-assert-if-true Model&HasAttachedMedia $record
     */
    protected static function usesAttachedMedia(Model $record): bool
    {
        return in_array(HasAttachedMedia::class, class_uses_recursive($record), true);
    }
}
