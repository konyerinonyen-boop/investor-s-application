<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view payments');
    }

    public function view(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('view payments');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create payments');
    }

    public function update(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('update payments');
    }

    public function delete(AuthUser $authUser, Payment $payment): bool
    {
        return $authUser->can('delete payments');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete payments');
    }

}