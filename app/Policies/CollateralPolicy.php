<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Collateral;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollateralPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Collateral');
    }

    public function view(AuthUser $authUser, Collateral $collateral): bool
    {
        return $authUser->can('View:Collateral');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Collateral');
    }

    public function update(AuthUser $authUser, Collateral $collateral): bool
    {
        return $authUser->can('Update:Collateral');
    }

    public function delete(AuthUser $authUser, Collateral $collateral): bool
    {
        return $authUser->can('Delete:Collateral');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Collateral');
    }

    public function restore(AuthUser $authUser, Collateral $collateral): bool
    {
        return $authUser->can('Restore:Collateral');
    }

    public function forceDelete(AuthUser $authUser, Collateral $collateral): bool
    {
        return $authUser->can('ForceDelete:Collateral');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Collateral');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Collateral');
    }

    public function replicate(AuthUser $authUser, Collateral $collateral): bool
    {
        return $authUser->can('Replicate:Collateral');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Collateral');
    }

}