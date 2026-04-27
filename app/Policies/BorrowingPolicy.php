<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Borrowing;
use Illuminate\Auth\Access\HandlesAuthorization;

class BorrowingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Borrowing');
    }

    public function view(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('View:Borrowing');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Borrowing');
    }

    public function update(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Update:Borrowing');
    }

    public function delete(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Delete:Borrowing');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Borrowing');
    }

    public function restore(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Restore:Borrowing');
    }

    public function forceDelete(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('ForceDelete:Borrowing');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Borrowing');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Borrowing');
    }

    public function replicate(AuthUser $authUser, Borrowing $borrowing): bool
    {
        return $authUser->can('Replicate:Borrowing');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Borrowing');
    }

}