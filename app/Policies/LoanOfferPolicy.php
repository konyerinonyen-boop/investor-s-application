<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LoanOffer;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanOfferPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view loan offers');
    }

    public function view(AuthUser $authUser, LoanOffer $loanOffer): bool
    {
        return $authUser->can('view loan offers');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create loan offers');
    }

    public function update(AuthUser $authUser, LoanOffer $loanOffer): bool
    {
        return $authUser->can('update loan offers');
    }

    public function delete(AuthUser $authUser, LoanOffer $loanOffer): bool
    {
        return $authUser->can('delete loan offers');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete loan offers');
    }

}