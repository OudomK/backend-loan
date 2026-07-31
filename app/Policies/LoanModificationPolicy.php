<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LoanModification;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanModificationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanModification');
    }

    public function view(AuthUser $authUser, LoanModification $loanModification): bool
    {
        return $authUser->can('View:LoanModification');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanModification');
    }

    public function update(AuthUser $authUser, LoanModification $loanModification): bool
    {
        return $authUser->can('Update:LoanModification');
    }

    public function delete(AuthUser $authUser, LoanModification $loanModification): bool
    {
        return $authUser->can('Delete:LoanModification');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LoanModification');
    }

    public function restore(AuthUser $authUser, LoanModification $loanModification): bool
    {
        return $authUser->can('Restore:LoanModification');
    }

    public function forceDelete(AuthUser $authUser, LoanModification $loanModification): bool
    {
        return $authUser->can('ForceDelete:LoanModification');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanModification');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanModification');
    }

    public function replicate(AuthUser $authUser, LoanModification $loanModification): bool
    {
        return $authUser->can('Replicate:LoanModification');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanModification');
    }

}