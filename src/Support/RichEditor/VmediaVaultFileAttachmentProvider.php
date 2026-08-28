<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support\RichEditor;

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\Integration\PluginVaultLibraryGallery;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Support\UploadGuard;

/**
 * Stores rich-editor uploads in the vmedia vault (fallback when drag/drop is used).
 */
final class VmediaVaultFileAttachmentProvider implements FileAttachmentProvider
{
    protected ?RichContentAttribute $attribute = null;

    public function __construct(
        protected string $vaultPlugin = 'voodbuilder',
    ) {}

    public function attribute(RichContentAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        if ($file instanceof MediaItem) {
            return MediaLibrary::publicUrl($file);
        }

        if (is_string($file) && Str::isUuid($file)) {
            $media = MediaItem::query()->where('uuid', $file)->first();

            return $media instanceof MediaItem ? MediaLibrary::publicUrl($media) : null;
        }

        return null;
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        UploadGuard::assertSafeUpload($file);

        $gallery = PluginVaultLibraryGallery::album($this->vaultPlugin);
        $stored = MediaLibrary::store($file, $gallery);

        return (string) $stored->uuid;
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return 'public';
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    public function cleanUpFileAttachments(array $exceptIds): void
    {
        // Vault files are managed by vmedia; do not delete on editor save.
    }
}
