<?php

namespace App\Repositories;

use App\Models\KycProfile;
use App\Models\User;

class KycProfileRepository
{
    public function findForUser(User $user): ?KycProfile
    {
        return $user->kycProfile()->first();
    }

    public function firstOrCreateForUser(User $user, array $attributes = []): KycProfile
    {
        return $user->kycProfile()->firstOrCreate(['user_id' => $user->id], $attributes);
    }

    public function updateForUser(User $user, array $data): KycProfile
    {
        $profile = $this->firstOrCreateForUser($user);
        $profile->fill($data);
        $profile->save();

        return $profile->fresh();
    }
}
