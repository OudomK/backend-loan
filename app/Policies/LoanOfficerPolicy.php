<?php

namespace App\Policies;

use App\Models\LoanOfficer;
use App\Models\User;

class LoanOfficerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function view(User $user, LoanOfficer $officer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, LoanOfficer $officer): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, LoanOfficer $officer): bool
    {
        return $user->role === 'admin';
    }
}
