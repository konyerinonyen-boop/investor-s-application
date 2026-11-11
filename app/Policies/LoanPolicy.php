<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Loan;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view loans');
    }

    public function view(AuthUser $authUser, Loan $loan): bool
    {
        return $authUser->can('view loans');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create loans');
    }

    public function update(AuthUser $authUser, Loan $loan): bool
    {
        return $authUser->can('update loans');
    }

    public function delete(AuthUser $authUser, Loan $loan): bool
    {
        return $authUser->can('delete loans');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete loans');
    }

}