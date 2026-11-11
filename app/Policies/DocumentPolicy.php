<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Document;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view documents');
    }

    public function view(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('view documents');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create documents');
    }

    public function update(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('update documents');
    }

    public function delete(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('delete documents');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete documents');
    }

}