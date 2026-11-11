<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WalletTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class WalletTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view wallet transactions');
    }

    public function view(AuthUser $authUser, WalletTransaction $walletTransaction): bool
    {
        return $authUser->can('view wallet transactions');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create wallet transactions');
    }

    public function update(AuthUser $authUser, WalletTransaction $walletTransaction): bool
    {
        return $authUser->can('update wallet transactions');
    }

    public function delete(AuthUser $authUser, WalletTransaction $walletTransaction): bool
    {
        return $authUser->can('delete wallet transactions');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete wallet transactions');
    }

}