<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Voodflow\Vmedia\Models\MediaGallery;

class MediaGalleryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MediaGallery');
    }

    public function view(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return $authUser->can('View:MediaGallery');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MediaGallery');
    }

    public function update(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return $authUser->can('Update:MediaGallery');
    }

    public function delete(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        // The default gallery is the fallback target for every upload: deleting it orphans
        // media that has nowhere else to live.
        if ($mediaGallery->is_default) {
            return false;
        }

        return $authUser->can('Delete:MediaGallery');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MediaGallery');
    }

    public function restore(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return $authUser->can('Restore:MediaGallery');
    }

    public function forceDelete(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return $authUser->can('ForceDelete:MediaGallery');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MediaGallery');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MediaGallery');
    }

    public function replicate(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return $authUser->can('Replicate:MediaGallery');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MediaGallery');
    }
}
