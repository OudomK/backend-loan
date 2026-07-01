<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Investor;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestorPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $user, string $ability): ?bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('investors', $user)) {
            return false;
        }
        return null;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Investor');
    }

    public function view(AuthUser $authUser, Investor $investor): bool
    {
        return $authUser->can('View:Investor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Investor');
    }

    public function update(AuthUser $authUser, Investor $investor): bool
    {
        return $authUser->can('Update:Investor');
    }

    public function delete(AuthUser $authUser, Investor $investor): bool
    {
        return $authUser->can('Delete:Investor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Investor');
    }

    public function restore(AuthUser $authUser, Investor $investor): bool
    {
        return $authUser->can('Restore:Investor');
    }

    public function forceDelete(AuthUser $authUser, Investor $investor): bool
    {
        return $authUser->can('ForceDelete:Investor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Investor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Investor');
    }

    public function replicate(AuthUser $authUser, Investor $investor): bool
    {
        return $authUser->can('Replicate:Investor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Investor');
    }

}
