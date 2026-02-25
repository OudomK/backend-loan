<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Loan $loan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->role === 'admin'; // Restrict loan modifications to Admin
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $user->role === 'admin';
    }
}
