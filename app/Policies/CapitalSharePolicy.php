<?php

namespace App\Policies;

use App\Models\CapitalShare;
use App\Models\User;

class CapitalSharePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function view(User $user, CapitalShare $share): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, CapitalShare $share): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, CapitalShare $share): bool
    {
        return $user->role === 'admin';
    }
}
