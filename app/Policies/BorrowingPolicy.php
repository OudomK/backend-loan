<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Borrowing;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BorrowingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Borrowing') || $authUser->can('ViewAny:SavingAccount');
    }

    public function view(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('View:Borrowing') || $authUser->can('View:SavingAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Borrowing') || $authUser->can('Create:SavingAccount');
    }

    public function update(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Update:Borrowing') || $authUser->can('Update:SavingAccount');
    }

    public function delete(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Delete:Borrowing') || $authUser->can('Delete:SavingAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Borrowing') || $authUser->can('DeleteAny:SavingAccount');
    }

    public function restore(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Restore:Borrowing') || $authUser->can('Restore:SavingAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Borrowing') || $authUser->can('RestoreAny:SavingAccount');
    }

    public function forceDelete(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('ForceDelete:Borrowing') || $authUser->can('ForceDelete:SavingAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Borrowing') || $authUser->can('ForceDeleteAny:SavingAccount');
    }

    public function replicate(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Replicate:Borrowing') || $authUser->can('Replicate:SavingAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Borrowing') || $authUser->can('Reorder:SavingAccount');
    }
}
