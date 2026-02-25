<?php

namespace App\Policies;

use App\Models\Borrower;
use App\Models\User;

class BorrowerPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Admin and Staff can view
    }

    public function view(User $user, Borrower $borrower): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function update(User $user, Borrower $borrower): bool
    {
        return $user->role === 'admin' || $user->role === 'staff';
    }

    public function delete(User $user, Borrower $borrower): bool
    {
        return $user->role === 'admin'; // Only Admin can delete
    }
}
