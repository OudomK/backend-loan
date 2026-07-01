<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Revenue;
use Illuminate\Auth\Access\HandlesAuthorization;

class RevenuePolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $user, string $ability): ?bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('revenues', $user)) {
            return false;
        }

        return null;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Revenue');
    }

    public function view(AuthUser $authUser, Revenue $revenue): bool
    {
        return $authUser->can('View:Revenue');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Revenue');
    }

    public function update(AuthUser $authUser, Revenue $revenue): bool
    {
        return $authUser->can('Update:Revenue');
    }

    public function delete(AuthUser $authUser, Revenue $revenue): bool
    {
        return $authUser->can('Delete:Revenue');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Revenue');
    }

    public function restore(AuthUser $authUser, Revenue $revenue): bool
    {
        return $authUser->can('Restore:Revenue');
    }

    public function forceDelete(AuthUser $authUser, Revenue $revenue): bool
    {
        return $authUser->can('ForceDelete:Revenue');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Revenue');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Revenue');
    }

    public function replicate(AuthUser $authUser, Revenue $revenue): bool
    {
        return $authUser->can('Replicate:Revenue');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Revenue');
    }
}
