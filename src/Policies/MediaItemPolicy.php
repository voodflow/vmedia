<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaLibrary;

class MediaItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MediaItem');
    }

    /**
     * The vault check scopes the panel to media this package owns. Without it the panel
     * permission reaches every MediaItem row, including records other packages manage.
     */
    public function view(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return $authUser->can('View:MediaItem') && MediaLibrary::isVaultMedia($mediaItem);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MediaItem');
    }

    public function update(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return $authUser->can('Update:MediaItem') && MediaLibrary::isVaultMedia($mediaItem);
    }

    public function delete(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return $authUser->can('Delete:MediaItem') && MediaLibrary::isVaultMedia($mediaItem);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MediaItem');
    }

    public function restore(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return $authUser->can('Restore:MediaItem');
    }

    public function forceDelete(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return $authUser->can('ForceDelete:MediaItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MediaItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MediaItem');
    }

    public function replicate(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return $authUser->can('Replicate:MediaItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MediaItem');
    }
}
