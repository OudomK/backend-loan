<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MiscellaneousTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class MiscellaneousTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MiscellaneousTransaction');
    }

    public function view(AuthUser $authUser, MiscellaneousTransaction $miscellaneousTransaction): bool
    {
        return $authUser->can('View:MiscellaneousTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MiscellaneousTransaction');
    }

    public function update(AuthUser $authUser, MiscellaneousTransaction $miscellaneousTransaction): bool
    {
        return $authUser->can('Update:MiscellaneousTransaction');
    }

    public function delete(AuthUser $authUser, MiscellaneousTransaction $miscellaneousTransaction): bool
    {
        return $authUser->can('Delete:MiscellaneousTransaction');
    }

    public function restore(AuthUser $authUser, MiscellaneousTransaction $miscellaneousTransaction): bool
    {
        return $authUser->can('Restore:MiscellaneousTransaction');
    }

    public function forceDelete(AuthUser $authUser, MiscellaneousTransaction $miscellaneousTransaction): bool
    {
        return $authUser->can('ForceDelete:MiscellaneousTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MiscellaneousTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MiscellaneousTransaction');
    }

    public function replicate(AuthUser $authUser, MiscellaneousTransaction $miscellaneousTransaction): bool
    {
        return $authUser->can('Replicate:MiscellaneousTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MiscellaneousTransaction');
    }

}