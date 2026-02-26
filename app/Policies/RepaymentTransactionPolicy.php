<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RepaymentTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class RepaymentTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RepaymentTransaction');
    }

    public function view(AuthUser $authUser, RepaymentTransaction $repaymentTransaction): bool
    {
        return $authUser->can('View:RepaymentTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RepaymentTransaction');
    }

    public function update(AuthUser $authUser, RepaymentTransaction $repaymentTransaction): bool
    {
        return $authUser->can('Update:RepaymentTransaction');
    }

    public function delete(AuthUser $authUser, RepaymentTransaction $repaymentTransaction): bool
    {
        return $authUser->can('Delete:RepaymentTransaction');
    }

    public function restore(AuthUser $authUser, RepaymentTransaction $repaymentTransaction): bool
    {
        return $authUser->can('Restore:RepaymentTransaction');
    }

    public function forceDelete(AuthUser $authUser, RepaymentTransaction $repaymentTransaction): bool
    {
        return $authUser->can('ForceDelete:RepaymentTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RepaymentTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RepaymentTransaction');
    }

    public function replicate(AuthUser $authUser, RepaymentTransaction $repaymentTransaction): bool
    {
        return $authUser->can('Replicate:RepaymentTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RepaymentTransaction');
    }

}