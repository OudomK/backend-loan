<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CapitalShareTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class CapitalShareTransactionPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $user, string $ability): ?bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('capital_share_transactions', $user)) {
            return false;
        }
        return null;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CapitalShareTransaction');
    }

    public function view(AuthUser $authUser, CapitalShareTransaction $capitalShareTransaction): bool
    {
        return $authUser->can('View:CapitalShareTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CapitalShareTransaction');
    }

    public function update(AuthUser $authUser, CapitalShareTransaction $capitalShareTransaction): bool
    {
        return $authUser->can('Update:CapitalShareTransaction');
    }

    public function delete(AuthUser $authUser, CapitalShareTransaction $capitalShareTransaction): bool
    {
        return $authUser->can('Delete:CapitalShareTransaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CapitalShareTransaction');
    }

    public function restore(AuthUser $authUser, CapitalShareTransaction $capitalShareTransaction): bool
    {
        return $authUser->can('Restore:CapitalShareTransaction');
    }

    public function forceDelete(AuthUser $authUser, CapitalShareTransaction $capitalShareTransaction): bool
    {
        return $authUser->can('ForceDelete:CapitalShareTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CapitalShareTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CapitalShareTransaction');
    }

    public function replicate(AuthUser $authUser, CapitalShareTransaction $capitalShareTransaction): bool
    {
        return $authUser->can('Replicate:CapitalShareTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CapitalShareTransaction');
    }

}
