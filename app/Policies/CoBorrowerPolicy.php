<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CoBorrower;
use Illuminate\Auth\Access\HandlesAuthorization;

class CoBorrowerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CoBorrower');
    }

    public function view(AuthUser $authUser, CoBorrower $coBorrower): bool
    {
        return $authUser->can('View:CoBorrower');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CoBorrower');
    }

    public function update(AuthUser $authUser, CoBorrower $coBorrower): bool
    {
        return $authUser->can('Update:CoBorrower');
    }

    public function delete(AuthUser $authUser, CoBorrower $coBorrower): bool
    {
        return $authUser->can('Delete:CoBorrower');
    }

    public function restore(AuthUser $authUser, CoBorrower $coBorrower): bool
    {
        return $authUser->can('Restore:CoBorrower');
    }

    public function forceDelete(AuthUser $authUser, CoBorrower $coBorrower): bool
    {
        return $authUser->can('ForceDelete:CoBorrower');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CoBorrower');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CoBorrower');
    }

    public function replicate(AuthUser $authUser, CoBorrower $coBorrower): bool
    {
        return $authUser->can('Replicate:CoBorrower');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CoBorrower');
    }

}