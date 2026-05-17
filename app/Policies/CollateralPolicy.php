<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Collateral;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollateralPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $user, string $ability): ?bool
    {
        if (!\App\Services\FeatureToggle::isAccessible('collateral_management', $user)) {
            return false;
        }

        return null;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return true;
    }

    public function view(AuthUser $authUser, Collateral $collateral): bool
    {
        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return true;
    }

    public function update(AuthUser $authUser, Collateral $collateral): bool
    {
        return true;
    }

    public function delete(AuthUser $authUser, Collateral $collateral): bool
    {
        return true;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return true;
    }

    public function restore(AuthUser $authUser, Collateral $collateral): bool
    {
        return true;
    }

    public function forceDelete(AuthUser $authUser, Collateral $collateral): bool
    {
        return true;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return true;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return true;
    }

    public function replicate(AuthUser $authUser, Collateral $collateral): bool
    {
        return true;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return true;
    }
}
