<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Repositories\KycProfileRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KycService
{
    public function __construct(
        protected KycProfileRepository $kycProfileRepository
    ) {
    }

    /**
     * Store the uploaded identity documents privately.
     *
     * Call this BEFORE contacting Bytid.
     * The returned document fields contain file paths,
     * not UploadedFile objects.
     */
    public function storeUploadedDocuments(array $data): array
    {
        $fileFields = [
            'id_document_path_front' => 'kycDoc/front',
            'id_document_path_back' => 'kycDoc/back',
        ];

        foreach ($fileFields as $field => $directory) {
            if (! isset($data[$field])) {
                continue;
            }

            $file = $data[$field];

            // Do not store the same file twice.
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->storeAs(
                $directory,
                Str::uuid() . '.' . $file->extension(),
                'local'
            );

            if ($path === false) {
                throw new \RuntimeException(
                    "Could not store the uploaded file: {$field}"
                );
            }

            $data[$field] = $path;
        }

        return $data;
    }

    /**
     * Save a KYC submission after the Bytid request succeeds.
     *
     * $bytidResult should contain:
     * [
     *     'reference' => '...',
     *     'provider_result' => [...]
     * ]
     */
    public function submit(
        User $user,
        array $data,
        array $bytidResult
    ): array {
        return $this->saveSubmission(
            $user,
            $data,
            $bytidResult
        );
    }

    /**
     * Update an existing KYC submission.
     */
    public function update(User $user, array $data): array
    {
        return $this->saveSubmission($user, $data);
    }

    protected function saveSubmission(
        User $user,
        array $data,
        ?array $bytidResult = null
    ): array {
        // Never allow the client to choose administrative fields.
        unset(
            $data['user_id'],
            $data['status'],
            $data['reviewed_by'],
            $data['reviewed_at'],
            $data['rejection_reason'],
            $data['submitted_ip']
        );

        // Supports existing callers that still pass UploadedFile objects.
        // Already-stored paths are left unchanged.
        $data = $this->storeUploadedFiles($data);

        $data['status'] = 'pending';
        $data['trigger_source'] = 'resubmission';
        $data['submitted_ip'] = request()->ip();
        $data['rejection_reason'] = null;

        // Save the reference returned by our Bytid submission method.
        if ($bytidResult !== null) {
            $reference = $bytidResult['reference'] ?? null;

            if (! is_string($reference) || $reference === '') {
                throw new \RuntimeException(
                    'The Bytid document reference is missing.'
                );
            }

            $data['identity_verification_reference'] = $reference;

            // The request succeeding does not mean the document
            // has passed all verification checks.
            $data['identity_verification_status'] = 'pending';
            $data['identity_verified_at'] = null;
        } else {
            $data['identity_verification_status'] = 'pending';
            $data['identity_verification_reference'] = null;
            $data['identity_verified_at'] = null;
        }

        // A new submission starts a new liveness verification flow.
        $data['selfie_verification_status'] = 'pending';
        $data['selfie_verification_reference'] = null;
        $data['selfie_verified_at'] = null;

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

    /**
     * Store any remaining uploaded files privately.
     */
    protected function storeUploadedFiles(array $data): array
    {
        $fileFields = [
            'id_document_path_front' => 'kycDoc/front',
            'id_document_path_back' => 'kycDoc/back',
            'selfie_path' => 'kycDoc/selfie',
        ];

        foreach ($fileFields as $field => $directory) {
            if (
                ! isset($data[$field]) ||
                ! $data[$field] instanceof UploadedFile
            ) {
                continue;
            }

            $file = $data[$field];

            $path = $file->storeAs(
                $directory,
                Str::uuid() . '.' . $file->extension(),
                'local'
            );

            if ($path === false) {
                throw new \RuntimeException(
                    "Could not store the uploaded file: {$field}"
                );
            }

            $data[$field] = $path;
        }

        return $data;
    }

    public function startBytidLiveness(
        KycProfile $profile,
        BytidVerificationService $bytid
    ): array {
        if (
            empty($profile->id_document_path_front) ||
            empty($profile->id_document_type)
        ) {
            throw ValidationException::withMessages([
                'kyc' => 'Submit your identity document before starting liveness verification.',
            ]);
        }

        return $bytid->startLiveness($profile);
    }

    public function completeBytidLiveness(
        KycProfile $profile,
        string $sessionId,
        BytidVerificationService $bytid
    ): array {
        if ($profile->selfie_verification_reference !== $sessionId) {
            abort(
                403,
                'The liveness session does not belong to this KYC application.'
            );
        }

        $session = $bytid->getLivenessSession($sessionId);

        $status = strtolower(
            (string) ($session['status'] ?? 'pending')
        );

        if (in_array($status, ['pending', 'processing'], true)) {
            return [
                'success' => true,
                'http_status' => 202,
                'message' => 'Liveness verification is still processing.',
                'data' => [
                    'liveness_status' => $status,
                ],
            ];
        }

        if ($status !== 'passed') {
            $profile->forceFill([
                'selfie_verification_status' => 'failed',
                'selfie_verified_at' => null,
            ])->save();

            return [
                'success' => false,
                'http_status' => 422,
                'message' => 'Liveness verification failed. Start a new session to retry.',
                'data' => [
                    'liveness_status' => $status,
                ],
            ];
        }

        // The webhook processes final verification results.
        return [
            'success' => true,
            'http_status' => 200,
            'message' => 'Liveness passed. The webhook will process verification results.',
            'data' => [
                'liveness_status' => 'passed',
            ],
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
