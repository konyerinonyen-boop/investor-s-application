<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EquityInvestment;
use Illuminate\Auth\Access\HandlesAuthorization;

class EquityInvestmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view equity investments');
    }

    public function view(AuthUser $authUser, EquityInvestment $equityInvestment): bool
    {
        return $authUser->can('view equity investments');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create equity investments');
    }

    public function update(AuthUser $authUser, EquityInvestment $equityInvestment): bool
    {
        return $authUser->can('update equity investments');
    }

    public function delete(AuthUser $authUser, EquityInvestment $equityInvestment): bool
    {
        return $authUser->can('delete equity investments');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete equity investments');
    }

}