<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\EquityRound;
use Illuminate\Auth\Access\HandlesAuthorization;

class EquityRoundPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view equity rounds');
    }

    public function view(AuthUser $authUser, EquityRound $equityRound): bool
    {
        return $authUser->can('view equity rounds');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create equity rounds');
    }

    public function update(AuthUser $authUser, EquityRound $equityRound): bool
    {
        return $authUser->can('update equity rounds');
    }

    public function delete(AuthUser $authUser, EquityRound $equityRound): bool
    {
        return $authUser->can('delete equity rounds');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete equity rounds');
    }

}