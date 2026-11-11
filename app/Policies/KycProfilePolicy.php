<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KycProfile;
use Illuminate\Auth\Access\HandlesAuthorization;

class KycProfilePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view KYC profiles');
    }

    public function view(AuthUser $authUser, KycProfile $kycProfile): bool
    {
        return $authUser->can('view KYC profiles');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create KYC profiles');
    }

    public function update(AuthUser $authUser, KycProfile $kycProfile): bool
    {
        return $authUser->can('update KYC profiles');
    }

    public function delete(AuthUser $authUser, KycProfile $kycProfile): bool
    {
        return $authUser->can('delete KYC profiles');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete KYC profiles');
    }

}