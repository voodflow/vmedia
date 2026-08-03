<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;

class MediaItemPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function view(?Authenticatable $user, MediaItem $media): bool
    {
        return $user !== null && MediaLibrary::isVaultMedia($media);
    }

    public function create(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function update(?Authenticatable $user, MediaItem $media): bool
    {
        return $user !== null && MediaLibrary::isVaultMedia($media);
    }

    public function delete(?Authenticatable $user, MediaItem $media): bool
    {
        return $user !== null && MediaLibrary::isVaultMedia($media);
    }
}
