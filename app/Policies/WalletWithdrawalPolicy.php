<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WalletWithdrawal;
use Illuminate\Auth\Access\HandlesAuthorization;

class WalletWithdrawalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view wallet withdrawals');
    }

    public function view(AuthUser $authUser, WalletWithdrawal $walletWithdrawal): bool
    {
        return $authUser->can('view wallet withdrawals');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create wallet withdrawals');
    }

    public function update(AuthUser $authUser, WalletWithdrawal $walletWithdrawal): bool
    {
        return $authUser->can('update wallet withdrawals');
    }

    public function delete(AuthUser $authUser, WalletWithdrawal $walletWithdrawal): bool
    {
        return $authUser->can('delete wallet withdrawals');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete wallet withdrawals');
    }

}