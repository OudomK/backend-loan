<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LoanProduct;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanProductPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanProduct');
    }

    public function view(AuthUser $authUser, LoanProduct $loanProduct): bool
    {
        return $authUser->can('View:LoanProduct');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanProduct');
    }

    public function update(AuthUser $authUser, LoanProduct $loanProduct): bool
    {
        return $authUser->can('Update:LoanProduct');
    }

    public function delete(AuthUser $authUser, LoanProduct $loanProduct): bool
    {
        return $authUser->can('Delete:LoanProduct');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LoanProduct');
    }

    public function restore(AuthUser $authUser, LoanProduct $loanProduct): bool
    {
        return $authUser->can('Restore:LoanProduct');
    }

    public function forceDelete(AuthUser $authUser, LoanProduct $loanProduct): bool
    {
        return $authUser->can('ForceDelete:LoanProduct');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanProduct');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanProduct');
    }

    public function replicate(AuthUser $authUser, LoanProduct $loanProduct): bool
    {
        return $authUser->can('Replicate:LoanProduct');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanProduct');
    }

}