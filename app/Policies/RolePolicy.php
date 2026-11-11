<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view roles');
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('view roles');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('manage roles');
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('manage roles');
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('manage roles');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('manage roles');
    }

}