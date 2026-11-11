<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view products');
    }

    public function view(AuthUser $authUser, Product $product): bool
    {
        return $authUser->can('view products');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create products');
    }

    public function update(AuthUser $authUser, Product $product): bool
    {
        return $authUser->can('update products');
    }

    public function delete(AuthUser $authUser, Product $product): bool
    {
        return $authUser->can('delete products');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete products');
    }

}