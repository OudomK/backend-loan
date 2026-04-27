<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PaymentQr;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentQrPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PaymentQr');
    }

    public function view(AuthUser $authUser, PaymentQr $paymentQr): bool
    {
        return $authUser->can('View:PaymentQr');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PaymentQr');
    }

    public function update(AuthUser $authUser, PaymentQr $paymentQr): bool
    {
        return $authUser->can('Update:PaymentQr');
    }

    public function delete(AuthUser $authUser, PaymentQr $paymentQr): bool
    {
        return $authUser->can('Delete:PaymentQr');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PaymentQr');
    }

    public function restore(AuthUser $authUser, PaymentQr $paymentQr): bool
    {
        return $authUser->can('Restore:PaymentQr');
    }

    public function forceDelete(AuthUser $authUser, PaymentQr $paymentQr): bool
    {
        return $authUser->can('ForceDelete:PaymentQr');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PaymentQr');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PaymentQr');
    }

    public function replicate(AuthUser $authUser, PaymentQr $paymentQr): bool
    {
        return $authUser->can('Replicate:PaymentQr');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PaymentQr');
    }

}