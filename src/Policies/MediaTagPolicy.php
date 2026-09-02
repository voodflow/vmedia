<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Voodflow\Vmedia\Models\MediaTag;

class MediaTagPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MediaTag');
    }

    public function view(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return $authUser->can('View:MediaTag');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MediaTag');
    }

    public function update(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return $authUser->can('Update:MediaTag');
    }

    public function delete(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return $authUser->can('Delete:MediaTag');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MediaTag');
    }

    public function restore(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return $authUser->can('Restore:MediaTag');
    }

    public function forceDelete(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return $authUser->can('ForceDelete:MediaTag');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MediaTag');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MediaTag');
    }

    public function replicate(AuthUser $authUser, MediaTag $mediaTag): bool
    {
        return $authUser->can('Replicate:MediaTag');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MediaTag');
    }
}
