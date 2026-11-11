<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RepaymentSchedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class RepaymentSchedulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view repayment schedules');
    }

    public function view(AuthUser $authUser, RepaymentSchedule $repaymentSchedule): bool
    {
        return $authUser->can('view repayment schedules');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create repayment schedules');
    }

    public function update(AuthUser $authUser, RepaymentSchedule $repaymentSchedule): bool
    {
        return $authUser->can('update repayment schedules');
    }

    public function delete(AuthUser $authUser, RepaymentSchedule $repaymentSchedule): bool
    {
        return $authUser->can('delete repayment schedules');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete repayment schedules');
    }

}