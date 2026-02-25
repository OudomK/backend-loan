<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, Payroll $payroll): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->role === 'admin';
    }
}
