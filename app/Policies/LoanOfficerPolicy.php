<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LoanOfficer;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanOfficerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanOfficer');
    }

    public function view(AuthUser $authUser, LoanOfficer $loanOfficer): bool
    {
        return $authUser->can('View:LoanOfficer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanOfficer');
    }

    public function update(AuthUser $authUser, LoanOfficer $loanOfficer): bool
    {
        return $authUser->can('Update:LoanOfficer');
    }

    public function delete(AuthUser $authUser, LoanOfficer $loanOfficer): bool
    {
        return $authUser->can('Delete:LoanOfficer');
    }

    public function restore(AuthUser $authUser, LoanOfficer $loanOfficer): bool
    {
        return $authUser->can('Restore:LoanOfficer');
    }

    public function forceDelete(AuthUser $authUser, LoanOfficer $loanOfficer): bool
    {
        return $authUser->can('ForceDelete:LoanOfficer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanOfficer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanOfficer');
    }

    public function replicate(AuthUser $authUser, LoanOfficer $loanOfficer): bool
    {
        return $authUser->can('Replicate:LoanOfficer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanOfficer');
    }

}