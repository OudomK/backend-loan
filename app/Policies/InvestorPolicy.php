<?php

namespace App\Policies;

use App\Models\Investor;
use App\Models\User;

class InvestorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function view(User $user, Investor $investor): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Investor $investor): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Investor $investor): bool
    {
        return $user->role === 'admin';
    }
}
