<?php

namespace App\Policies;

use App\Models\RepaymentTransaction;
use App\Models\User;

class RepaymentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RepaymentTransaction $transaction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function update(User $user, RepaymentTransaction $transaction): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, RepaymentTransaction $transaction): bool
    {
        return $user->role === 'admin';
    }
}
