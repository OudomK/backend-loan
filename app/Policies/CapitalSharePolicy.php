<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CapitalShare;
use Illuminate\Auth\Access\HandlesAuthorization;

class CapitalSharePolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $user, string $ability): ?bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('capital_shares', $user)) {
            return false;
        }
        return null;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CapitalShare');
    }

    public function view(AuthUser $authUser, CapitalShare $capitalShare): bool
    {
        return $authUser->can('View:CapitalShare');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CapitalShare');
    }

    public function update(AuthUser $authUser, CapitalShare $capitalShare): bool
    {
        return $authUser->can('Update:CapitalShare');
    }

    public function delete(AuthUser $authUser, CapitalShare $capitalShare): bool
    {
        return $authUser->can('Delete:CapitalShare');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CapitalShare');
    }

    public function restore(AuthUser $authUser, CapitalShare $capitalShare): bool
    {
        return $authUser->can('Restore:CapitalShare');
    }

    public function forceDelete(AuthUser $authUser, CapitalShare $capitalShare): bool
    {
        return $authUser->can('ForceDelete:CapitalShare');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CapitalShare');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CapitalShare');
    }

    public function replicate(AuthUser $authUser, CapitalShare $capitalShare): bool
    {
        return $authUser->can('Replicate:CapitalShare');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CapitalShare');
    }

}
