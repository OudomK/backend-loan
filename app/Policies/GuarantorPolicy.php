<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Guarantor;
use Illuminate\Auth\Access\HandlesAuthorization;

class GuarantorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Guarantor');
    }

    public function view(AuthUser $authUser, Guarantor $guarantor): bool
    {
        return $authUser->can('View:Guarantor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Guarantor');
    }

    public function update(AuthUser $authUser, Guarantor $guarantor): bool
    {
        return $authUser->can('Update:Guarantor');
    }

    public function delete(AuthUser $authUser, Guarantor $guarantor): bool
    {
        return $authUser->can('Delete:Guarantor');
    }

    public function restore(AuthUser $authUser, Guarantor $guarantor): bool
    {
        return $authUser->can('Restore:Guarantor');
    }

    public function forceDelete(AuthUser $authUser, Guarantor $guarantor): bool
    {
        return $authUser->can('ForceDelete:Guarantor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Guarantor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Guarantor');
    }

    public function replicate(AuthUser $authUser, Guarantor $guarantor): bool
    {
        return $authUser->can('Replicate:Guarantor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Guarantor');
    }

}