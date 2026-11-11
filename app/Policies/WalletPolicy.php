<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Wallet;
use Illuminate\Auth\Access\HandlesAuthorization;

class WalletPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view wallets');
    }

    public function view(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('view wallets');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create wallets');
    }

    public function update(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('update wallets');
    }

    public function delete(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('delete wallets');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete wallets');
    }

}