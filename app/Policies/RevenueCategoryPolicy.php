<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RevenueCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class RevenueCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RevenueCategory');
    }

    public function view(AuthUser $authUser, RevenueCategory $revenueCategory): bool
    {
        return $authUser->can('View:RevenueCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RevenueCategory');
    }

    public function update(AuthUser $authUser, RevenueCategory $revenueCategory): bool
    {
        return $authUser->can('Update:RevenueCategory');
    }

    public function delete(AuthUser $authUser, RevenueCategory $revenueCategory): bool
    {
        return $authUser->can('Delete:RevenueCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RevenueCategory');
    }

    public function restore(AuthUser $authUser, RevenueCategory $revenueCategory): bool
    {
        return $authUser->can('Restore:RevenueCategory');
    }

    public function forceDelete(AuthUser $authUser, RevenueCategory $revenueCategory): bool
    {
        return $authUser->can('ForceDelete:RevenueCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RevenueCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RevenueCategory');
    }

    public function replicate(AuthUser $authUser, RevenueCategory $revenueCategory): bool
    {
        return $authUser->can('Replicate:RevenueCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RevenueCategory');
    }

}