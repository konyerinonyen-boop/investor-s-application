<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\KycProfileRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycService
{
    public function __construct(
        protected KycProfileRepository $kycProfileRepository
    ) {
    }

    public function update(User $user, array $data): array
    {
        return $this->saveSubmission($user, $data);
    }

    public function submit(User $user, array $data): array
    {
        return $this->saveSubmission($user, $data);
    }

    protected function saveSubmission(User $user, array $data): array
    {
        // Never allow the client to choose the overall KYC status.
        unset(
            $data['user_id'],
            $data['status'],
            $data['reviewed_by'],
            $data['reviewed_at'],
            $data['rejection_reason'],
            $data['submitted_ip']
        );

        $data = $this->storeUploadedFiles($data);

        $data['status'] = 'pending';
        $data['trigger_source'] = 'user';
        $data['submitted_ip'] = request()->ip();
        $data['rejection_reason'] = null;

        // Reset verification results when a new submission is made.
        $data['identity_verification_status'] = 'pending';
        $data['identity_verification_reference'] = null;
        $data['identity_verified_at'] = null;

        $data['selfie_verification_status'] = 'pending';
        $data['selfie_verification_reference'] = null;
        $data['selfie_verified_at'] = null;

        $data['bank_verification_status'] = 'pending';
        $data['bank_verification_reference'] = null;
        $data['bank_verified_at'] = null;

        $profile = DB::transaction(function () use ($user, $data) {
            $profile = $this->kycProfileRepository
                ->updateForUser($user, $data);

            $user->forceFill([
                'kyc_status' => 'pending',
            ])->save();

            return $profile;
        });

        return [
            'user' => $user->fresh(),
            'profile' => $profile,
        ];
    }

    protected function storeUploadedFiles(array $data): array
    {
        $fileFields = [
            'id_document_path_front' => 'kycDoc/front',
            'id_document_path_back' => 'kycDoc/back',
            'selfie_path' => 'kycDoc/selfie',
        ];

        foreach ($fileFields as $field => $directory) {
            if (
                isset($data[$field]) &&
                $data[$field] instanceof UploadedFile
            ) {
                $file = $data[$field];

                $data[$field] = $file->storeAs(
                    $directory,
                    Str::uuid() . '.' . $file->extension(),
                    'public'
                );
            }
        }

        return $data;
    }

    public function status(User $user): array
    {
        return [
            'kyc_status' => $user->kyc_status,
            'profile' => $user->kycProfile,
        ];
    }
}
