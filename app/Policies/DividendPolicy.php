<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Dividend;
use Illuminate\Auth\Access\HandlesAuthorization;

class DividendPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Dividend');
    }

    public function view(AuthUser $authUser, Dividend $dividend): bool
    {
        return $authUser->can('View:Dividend');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Dividend');
    }

    public function update(AuthUser $authUser, Dividend $dividend): bool
    {
        return $authUser->can('Update:Dividend');
    }

    public function delete(AuthUser $authUser, Dividend $dividend): bool
    {
        return $authUser->can('Delete:Dividend');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Dividend');
    }

    public function restore(AuthUser $authUser, Dividend $dividend): bool
    {
        return $authUser->can('Restore:Dividend');
    }

    public function forceDelete(AuthUser $authUser, Dividend $dividend): bool
    {
        return $authUser->can('ForceDelete:Dividend');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Dividend');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Dividend');
    }

    public function replicate(AuthUser $authUser, Dividend $dividend): bool
    {
        return $authUser->can('Replicate:Dividend');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Dividend');
    }

}