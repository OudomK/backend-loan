<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SavingAccount;
use Illuminate\Auth\Access\HandlesAuthorization;

class SavingAccountPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SavingAccount');
    }

    public function view(AuthUser $authUser, SavingAccount $savingAccount): bool
    {
        return $authUser->can('View:SavingAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SavingAccount');
    }

    public function update(AuthUser $authUser, SavingAccount $savingAccount): bool
    {
        return $authUser->can('Update:SavingAccount');
    }

    public function delete(AuthUser $authUser, SavingAccount $savingAccount): bool
    {
        return $authUser->can('Delete:SavingAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SavingAccount');
    }

    public function restore(AuthUser $authUser, SavingAccount $savingAccount): bool
    {
        return $authUser->can('Restore:SavingAccount');
    }

    public function forceDelete(AuthUser $authUser, SavingAccount $savingAccount): bool
    {
        return $authUser->can('ForceDelete:SavingAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SavingAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SavingAccount');
    }

    public function replicate(AuthUser $authUser, SavingAccount $savingAccount): bool
    {
        return $authUser->can('Replicate:SavingAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SavingAccount');
    }

}