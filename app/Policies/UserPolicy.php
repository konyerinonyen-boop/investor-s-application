<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view users');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('view users');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create users');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('update users');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('delete users');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete users');
    }

}