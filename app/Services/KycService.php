<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\KycProfileRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycService
{
    public function __construct(protected KycProfileRepository $kycProfileRepository)
    {
    }

    public function update(User $user, array $data): array
    {
        $data = $this->FileStoreAndName($data);
        $data['status'] = 'pending';
        $profile = $this->kycProfileRepository->updateForUser($user, $data);

        $user->forceFill(['kyc_status' => 'pending'])->save();
        return [
            'user' => $user->fresh(),
            'profile' => $profile,
        ];
    }

    public function submit(User $user, array $data): array
    {
         $data = $this->FileStoreAndName($data);
        $profile = $this->kycProfileRepository->firstOrCreateForUser($user, $data);
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

protected function fileStoreAndName(array $data): array
{
    $uniqueFileName = 'Kyc' . Str::uuid();

    if (
        isset($data['id_document_path_front']) &&
        $data['id_document_path_front'] instanceof UploadedFile
    ) {
        $file = $data['id_document_path_front'];

        $data['id_document_path_front'] = $file->storeAs(
            'kycDoc/front',
            $uniqueFileName . '.' . $file->getClientOriginalExtension(),
            'public'
        );
    }

    if (
        isset($data['id_document_path_back']) &&
        $data['id_document_path_back'] instanceof UploadedFile
    ) {
        $file = $data['id_document_path_back'];

        $data['id_document_path_back'] = $file->storeAs(
            'kycDoc/back',
            $uniqueFileName . '.' . $file->getClientOriginalExtension(),
            'public'
        );
    }

    if (
        isset($data['selfie_path']) &&
        $data['selfie_path'] instanceof UploadedFile
    ) {
        $file = $data['selfie_path'];

        $data['selfie_path'] = $file->storeAs(
            'kycDoc/selfie',
            $uniqueFileName . '.' . $file->getClientOriginalExtension(),
            'public'
        );
    }

    return $data;
}
}
