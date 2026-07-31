<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Relationship;
use Illuminate\Auth\Access\HandlesAuthorization;

class RelationshipPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Relationship');
    }

    public function view(AuthUser $authUser, Relationship $relationship): bool
    {
        return $authUser->can('View:Relationship');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Relationship');
    }

    public function update(AuthUser $authUser, Relationship $relationship): bool
    {
        return $authUser->can('Update:Relationship');
    }

    public function delete(AuthUser $authUser, Relationship $relationship): bool
    {
        return $authUser->can('Delete:Relationship');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Relationship');
    }

    public function restore(AuthUser $authUser, Relationship $relationship): bool
    {
        return $authUser->can('Restore:Relationship');
    }

    public function forceDelete(AuthUser $authUser, Relationship $relationship): bool
    {
        return $authUser->can('ForceDelete:Relationship');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Relationship');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Relationship');
    }

    public function replicate(AuthUser $authUser, Relationship $relationship): bool
    {
        return $authUser->can('Replicate:Relationship');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Relationship');
    }

}