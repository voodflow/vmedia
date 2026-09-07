<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Voodflow\Vmedia\Models\MediaTag;
use Voodflow\Vmedia\Support\MediaAuthorization;

class MediaTagPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'ViewAny:MediaTag');
    }

    public function view(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return MediaAuthorization::allows($authUser, 'View:MediaTag');
    }

    public function create(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'Create:MediaTag');
    }

    public function update(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return MediaAuthorization::allows($authUser, 'Update:MediaTag');
    }

    public function delete(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return MediaAuthorization::allows($authUser, 'Delete:MediaTag');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'DeleteAny:MediaTag');
    }

    public function restore(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return MediaAuthorization::allows($authUser, 'Restore:MediaTag');
    }

    public function forceDelete(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return MediaAuthorization::allows($authUser, 'ForceDelete:MediaTag');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'ForceDeleteAny:MediaTag');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'RestoreAny:MediaTag');
    }

    public function replicate(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return MediaAuthorization::allows($authUser, 'Replicate:MediaTag');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'Reorder:MediaTag');
    }
}
