<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Support\MediaAuthorization;

class MediaGalleryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'ViewAny:MediaGallery');
    }

    public function view(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return MediaAuthorization::allows($authUser, 'View:MediaGallery');
    }

    public function create(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'Create:MediaGallery');
    }

    public function update(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return MediaAuthorization::allows($authUser, 'Update:MediaGallery');
    }

    public function delete(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        // The default gallery is the fallback target for every upload: deleting it orphans
        // media that has nowhere else to live.
        if ($mediaGallery->is_default) {
            return false;
        }

        return MediaAuthorization::allows($authUser, 'Delete:MediaGallery');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'DeleteAny:MediaGallery');
    }

    public function restore(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return MediaAuthorization::allows($authUser, 'Restore:MediaGallery');
    }

    public function forceDelete(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return MediaAuthorization::allows($authUser, 'ForceDelete:MediaGallery');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'ForceDeleteAny:MediaGallery');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'RestoreAny:MediaGallery');
    }

    public function replicate(AuthUser $authUser, MediaGallery $mediaGallery): bool
    {
        return MediaAuthorization::allows($authUser, 'Replicate:MediaGallery');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return MediaAuthorization::allows($authUser, 'Reorder:MediaGallery');
    }
}
