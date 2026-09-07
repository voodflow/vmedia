<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaAuthorization;
use Voodflow\Vmedia\Support\MediaLibrary;

class MediaItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'ViewAny:MediaItem');
    }

    /**
     * The vault check scopes the panel to media this package owns. Without it the panel
     * permission reaches every MediaItem row, including records other packages manage.
     */
    public function view(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return MediaAuthorization::allows($authUser, 'View:MediaItem')
            && MediaLibrary::isVaultMedia($mediaItem);
    }

    public function create(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'Create:MediaItem');
    }

    public function update(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return MediaAuthorization::allows($authUser, 'Update:MediaItem')
            && MediaLibrary::isVaultMedia($mediaItem);
    }

    public function delete(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return MediaAuthorization::allows($authUser, 'Delete:MediaItem')
            && MediaLibrary::isVaultMedia($mediaItem);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'DeleteAny:MediaItem');
    }

    public function restore(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return MediaAuthorization::allows($authUser, 'Restore:MediaItem');
    }

    public function forceDelete(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return MediaAuthorization::allows($authUser, 'ForceDelete:MediaItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'ForceDeleteAny:MediaItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'RestoreAny:MediaItem');
    }

    public function replicate(AuthUser $authUser, MediaItem $mediaItem): bool
    {
        return MediaAuthorization::allows($authUser, 'Replicate:MediaItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'Reorder:MediaItem');
    }
}
