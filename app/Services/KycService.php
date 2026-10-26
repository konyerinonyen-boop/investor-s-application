<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Repositories\KycProfileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KycService
{
    public function __construct(
        private readonly KycProfileRepository $profiles,
        private readonly SumsubVerificationService $sumsub,
    ) {}

    /**
     * Return the user's current KYC status and profile.
     */
    public function status(User $user): array
    {
        return [
            'kyc_status' => $user->kyc_status,
            'profile' => $this->profiles->findForUser($user),
        ];
    }

    /**
     * Save KYC details without submitting them for review.
     */
    public function update(User $user, array $data): KycProfile
    {
        $data = $this->storeUploadedDocuments($data);

        unset(
            $data['id_document_front'],
            $data['id_document_back']
        );

        $profile = $this->profiles->updateForUser($user, $data);

        Log::info('KYC: Profile details updated.', [
            'user_id' => $user->id,
            'profile_id' => $profile->id,
        ]);

        return $profile;
    }

    /**
     * Save and submit KYC details for verification.
     */
    public function submit(User $user, array $data): KycProfile
    {
        $data = $this->storeUploadedDocuments($data);

        unset(
            $data['id_document_front'],
            $data['id_document_back']
        );

        return DB::transaction(function () use ($user, $data) {
            $profile = $this->profiles->firstOrCreateForUser($user);

            $previousStatus = $profile->status;

            $profile->fill($data);

            $profile->forceFill([
                'status' => 'pending',
                'trigger_source' => 'resubmission',
                'submitted_ip' => request()->ip(),
                'identity_verification_status' => 'pending',
                'identity_verified_at' => null,
                'selfie_verification_status' => 'pending',
                'selfie_verified_at' => null,
                'rejection_reason' => null,
            ])->save();

            $user->forceFill([
                'kyc_status' => 'pending',
            ])->save();

            Log::info('KYC: Submission saved.', [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'previous_status' => $previousStatus,
                'new_status' => 'pending',
            ]);

            return $profile->fresh();
        });
    }

    /**
     * Start a Sumsub verification session.
     */
    public function startSumsub(User $user): array
    {
        $profile = $this->profiles->firstOrCreateForUser(
            $user,
            ['status' => 'not_started']
        );

        if ($profile->status === 'approved') {
            Log::notice('Sumsub: Verification already approved.', [
                'user_id' => $user->id,
            ]);

            abort(409, 'Your identity verification is already approved.');
        }

        Log::info('Sumsub: Starting verification.', [
            'user_id' => $user->id,
            'level_name' => config('services.sumsub.level_name'),
        ]);

        try {
            $result = $this->sumsub->createAccessToken($user);

            Log::info('Sumsub: Verification session created.', [
                'user_id' => $user->id,
                'expires_in' => $result['expires_in'] ?? null,
            ]);

            return [
                'token' => $result['token'],
                'expires_in' => $result['expires_in'] ?? 600,
            ];
        } catch (\Throwable $exception) {
            Log::error('Sumsub: Failed to create verification session.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Process Sumsub webhook events.
     */
    public function processSumsubWebhook(array $payload): void
    {
        $eventType = $payload['type'] ?? 'unknown';

        if ($eventType !== 'applicantReviewed') {
            Log::info('Sumsub: Webhook event ignored.', [
                'event_type' => $eventType,
            ]);

            return;
        }

        $externalUserId = $payload['externalUserId']
            ?? $payload['applicant']['externalUserId']
            ?? null;

        $applicantId = $payload['applicantId']
            ?? $payload['applicant']['id']
            ?? null;

        if (! $externalUserId) {
            Log::warning('Sumsub: Webhook has no external user ID.', [
                'event_type' => $eventType,
                'applicant_id' => $applicantId,
            ]);

            return;
        }

        $user = User::query()->find($externalUserId);

        if (! $user) {
            Log::warning('Sumsub: User not found for webhook.', [
                'external_user_id' => $externalUserId,
                'applicant_id' => $applicantId,
            ]);

            return;
        }

        DB::transaction(function () use (
            $payload,
            $user,
            $applicantId,
            $eventType
        ) {
            $profile = $this->profiles->firstOrCreateForUser($user);

            $previousStatus = $profile->status;
            $previousIdentityStatus = $profile->identity_verification_status;

            $reviewStatus = $payload['reviewStatus'] ?? null;
            $reviewAnswer = $payload['reviewResult']['reviewAnswer'] 
                ?? $payload['reviewAnswer']
                ?? null;

            $profileChanges = [];

            if ($applicantId) {
                $profileChanges['sumsub_applicant_id'] = $applicantId;
            }

            if (
                $reviewStatus === 'completed'
                && $reviewAnswer === 'GREEN'
            ) {
                $profileChanges['identity_verification_status'] = 'verified';
                $profileChanges['identity_verified_at'] = now();

                /*
                 * Only mark selfie verification as verified if your
                 * Sumsub level actually verifies the required selfie
                 * or liveness check.
                 *
                 * Do not automatically approve the entire KYC profile
                 * until the required bank verification is complete.
                 */

                $bankVerified = in_array(
                    $profile->bank_verification_status,
                    ['verified', 'approved'],
                    true
                );

                $profileChanges['status'] = $bankVerified
                    ? 'approved'
                    : 'pending';

                $profileChanges['rejection_reason'] = null;

                $user->forceFill([
                    'kyc_status' => $bankVerified
                        ? 'approved'
                        : 'pending',
                ])->save();

                Log::info('Sumsub: Identity review passed.', [
                    'user_id' => $user->id,
                    'applicant_id' => $applicantId,
                    'bank_verified' => $bankVerified,
                ]);
            } elseif (
                $reviewStatus === 'completed'
                && $reviewAnswer === 'RED'
            ) {
                $reason = $payload['reviewResult']['clientComment']
                    ?? $payload['reviewResult']['moderationComment']
                    ?? 'Identity verification was rejected.';

                $profileChanges['identity_verification_status'] = 'rejected';
                $profileChanges['status'] = 'rejected';
                $profileChanges['rejection_reason'] = $reason;

                $user->forceFill([
                    'kyc_status' => 'rejected',
                ])->save();

                Log::warning('Sumsub: Identity review rejected.', [
                    'user_id' => $user->id,
                    'applicant_id' => $applicantId,
                    'reason' => $reason,
                ]);
            } else {
                Log::info('Sumsub: Review is not final.', [
                    'user_id' => $user->id,
                    'applicant_id' => $applicantId,
                    'review_status' => $reviewStatus,
                    'review_answer' => $reviewAnswer,
                ]);
            }

            if ($profileChanges !== []) {
                $profile->forceFill($profileChanges)->save();
            }

            Log::info('KYC: Sumsub webhook processing completed.', [
                'user_id' => $user->id,
                'applicant_id' => $applicantId,
                'event_type' => $eventType,
                'previous_status' => $previousStatus,
                'new_status' => $profile->status,
                'previous_identity_status' => $previousIdentityStatus,
                'new_identity_status' => $profile->identity_verification_status,
            ]);
        });
    }

    /**
     * Store uploaded documents privately.
     */
    private function storeUploadedDocuments(array $data): array
    {
        foreach ([
            'id_document_front' => 'id_document_path_front',
            'id_document_back' => 'id_document_path_back',
        ] as $fileKey => $pathKey) {
            if (
                isset($data[$fileKey])
                && $data[$fileKey] instanceof \Illuminate\Http\UploadedFile
            ) {
                $data[$pathKey] = $data[$fileKey]->store(
                    'kycDoc',
                    'private'
                );

                unset($data[$fileKey]);
            }
        }

        return $data;
    }
}
