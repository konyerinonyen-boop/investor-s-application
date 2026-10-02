<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\KycProfileRepository;

class KycService
{
    public function __construct(protected KycProfileRepository $kycProfileRepository)
    {
    }

    public function start(User $user): array
    {
        $profile = $this->kycProfileRepository->firstOrCreateForUser($user, [
            'status' => 'in_progress',
            'review_notes' => null,
        ]);

        $user->forceFill(['kyc_status' => 'in_progress'])->save();

        return [
            'user' => $user->fresh(),
            'profile' => $profile,
        ];
    }

    public function submit(User $user, array $data): array
    {
        $profile = $this->kycProfileRepository->updateForUser($user, $data);
        $user->forceFill(['kyc_status' => 'pending'])->save();

        return [
            'user' => $user->fresh(),
            'profile' => $profile,
        ];
    }

    public function status(User $user): array
    {
        return [
            'kyc_status' => $user->kyc_status,
            'profile' => $user->kycProfile,
        ];
    }
}
