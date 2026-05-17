<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BorrowingRepayment;
use Illuminate\Auth\Access\HandlesAuthorization;

class BorrowingRepaymentPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $user, string $ability): ?bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('savings', $user)) {
            return false;
        }
        return null;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BorrowingRepayment');
    }

    public function view(AuthUser $authUser, BorrowingRepayment $borrowingRepayment): bool
    {
        return $authUser->can('View:BorrowingRepayment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BorrowingRepayment');
    }

    public function update(AuthUser $authUser, BorrowingRepayment $borrowingRepayment): bool
    {
        return $authUser->can('Update:BorrowingRepayment');
    }

    public function delete(AuthUser $authUser, BorrowingRepayment $borrowingRepayment): bool
    {
        return $authUser->can('Delete:BorrowingRepayment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BorrowingRepayment');
    }

    public function restore(AuthUser $authUser, BorrowingRepayment $borrowingRepayment): bool
    {
        return $authUser->can('Restore:BorrowingRepayment');
    }

    public function forceDelete(AuthUser $authUser, BorrowingRepayment $borrowingRepayment): bool
    {
        return $authUser->can('ForceDelete:BorrowingRepayment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BorrowingRepayment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BorrowingRepayment');
    }

    public function replicate(AuthUser $authUser, BorrowingRepayment $borrowingRepayment): bool
    {
        return $authUser->can('Replicate:BorrowingRepayment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BorrowingRepayment');
    }

}
