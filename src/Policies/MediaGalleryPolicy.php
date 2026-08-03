<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Voodflow\Vmedia\Models\MediaGallery;

class MediaGalleryPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function view(?Authenticatable $user, MediaGallery $gallery): bool
    {
        return $user !== null;
    }

    public function create(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function update(?Authenticatable $user, MediaGallery $gallery): bool
    {
        return $user !== null;
    }

    public function delete(?Authenticatable $user, MediaGallery $gallery): bool
    {
        return $user !== null && ! $gallery->is_default;
    }
}
