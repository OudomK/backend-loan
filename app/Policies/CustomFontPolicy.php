<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CustomFont;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomFontPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CustomFont');
    }

    public function view(AuthUser $authUser, CustomFont $customFont): bool
    {
        return $authUser->can('View:CustomFont');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CustomFont');
    }

    public function update(AuthUser $authUser, CustomFont $customFont): bool
    {
        return $authUser->can('Update:CustomFont');
    }

    public function delete(AuthUser $authUser, CustomFont $customFont): bool
    {
        return $authUser->can('Delete:CustomFont');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CustomFont');
    }

    public function restore(AuthUser $authUser, CustomFont $customFont): bool
    {
        return $authUser->can('Restore:CustomFont');
    }

    public function forceDelete(AuthUser $authUser, CustomFont $customFont): bool
    {
        return $authUser->can('ForceDelete:CustomFont');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomFont');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomFont');
    }

    public function replicate(AuthUser $authUser, CustomFont $customFont): bool
    {
        return $authUser->can('Replicate:CustomFont');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomFont');
    }

}