<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Notification;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view notifications');
    }

    public function view(AuthUser $authUser, Notification $notification): bool
    {
        return $authUser->can('view notifications');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create notifications');
    }

    public function update(AuthUser $authUser, Notification $notification): bool
    {
        return $authUser->can('update notifications');
    }

    public function delete(AuthUser $authUser, Notification $notification): bool
    {
        return $authUser->can('delete notifications');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete notifications');
    }

}