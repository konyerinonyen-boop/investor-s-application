<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RepaymentPayout;
use Illuminate\Auth\Access\HandlesAuthorization;

class RepaymentPayoutPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RepaymentPayout');
    }

    public function view(AuthUser $authUser, RepaymentPayout $repaymentPayout): bool
    {
        return $authUser->can('View:RepaymentPayout');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RepaymentPayout');
    }

    public function update(AuthUser $authUser, RepaymentPayout $repaymentPayout): bool
    {
        return $authUser->can('Update:RepaymentPayout');
    }

    public function delete(AuthUser $authUser, RepaymentPayout $repaymentPayout): bool
    {
        return $authUser->can('Delete:RepaymentPayout');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RepaymentPayout');
    }

}