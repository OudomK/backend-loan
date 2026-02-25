<?php

namespace App\Policies;

use App\Models\SavingAccount;
use App\Models\User;

class SavingAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavingAccount $account): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function update(User $user, SavingAccount $account): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function delete(User $user, SavingAccount $account): bool
    {
        return $user->role === 'admin';
    }
}
