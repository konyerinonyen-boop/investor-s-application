<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Repositories\KycProfileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class KycService
{
    public function __construct(
        private readonly KycProfileRepository $profiles,
        private readonly SumsubVerificationService $sumsub,
        private readonly PaystackService $paystack,
    ) {}

    /**
     * Return the user's current KYC status.
     */
    public function status(User $user): array
    {
        return [
            'kyc_status' => $user->kyc_status,
            'profile' => $this->profiles->findForUser($user),
        ];
    }

    /**
     * Save bank details only. Provider-owned fields cannot be edited here.
     */
   
public function update(User $user, array $data): KycProfile
{
    $bankCode = (string) $data['bank_code'];
    $accountNumber = (string) $data['account_number'];

    $profile = $this->profiles->firstOrCreateForUser($user);

    if ($profile->status === 'verified') {
        abort(409, 'Your KYC verification is already complete.');
    }

    // Get the official bank list from Paystack.
    try {
        $banks = $this->paystack->listBanks();
    } catch (\Throwable $exception) {
        Log::error('Paystack: Could not retrieve bank list.', [
            'user_id' => $user->id,
            'exception' => $exception::class,
        ]);

        throw ValidationException::withMessages([
            'bank_code' =>
                'We could not load the bank information. Please try again shortly.',
        ]);
    }

    // Do not trust a bank code just because the browser submitted it.
    $selectedBank = collect($banks)->first(
        fn (array $bank) => $bank['code'] === $bankCode
    );

    if (! $selectedBank) {
        throw ValidationException::withMessages([
            'bank_code' =>
                'Please select a valid bank from the bank list.',
        ]);
    }

    // Save the selected bank and mark verification as pending.
    $profile->forceFill([
        'bank_name' => $selectedBank['name'],
        'bank_code' => $selectedBank['code'],
        'account_number' => $accountNumber,
        'account_name' => null,
        'bank_verification_status' => 'pending',
        'bank_verification_reference' => null,
        'bank_verified_at' => null,
        'submitted_ip' => request()->ip(),
    ])->save();

    try {
        $resolved = $this->paystack->resolveAccount(
            $accountNumber,
            $selectedBank['code'],
        );
    } catch (\Throwable $exception) {
        $profile->forceFill([
            'bank_verification_status' => 'failed',
            'account_name' => null,
            'bank_verified_at' => null,
        ])->save();

        Log::warning('Paystack: Bank account resolution failed.', [
            'user_id' => $user->id,
            'bank_code' => $selectedBank['code'],
            'exception' => $exception::class,
        ]);

        throw ValidationException::withMessages([
            'account_number' =>
                'Paystack could not verify this account. Check your bank and account number, then try again.',
        ]);
    }

    // Store only the account name returned by Paystack.
    $profile->account_name = $resolved['account_name'];
    $profile->bank_verification_status = 'verified';
    $profile->bank_verified_at = now();

    $identityVerified =
        $profile->identity_verification_status === 'verified';

    $profile->status = $identityVerified ? 'verified' : 'pending';
    $profile->save();

    $user->forceFill([
        'kyc_status' => $profile->status,
    ])->save();

    Log::info('Paystack: Bank account verified.', [
        'user_id' => $user->id,
        'profile_id' => $profile->id,
        'identity_verified' => $identityVerified,
    ]);

    return $profile->fresh();
}

    /**
     * Start or resume the Sumsub verification session.
     */
    public function startSumsub(User $user): array
    {
        $profile = $this->profiles->firstOrCreateForUser($user);

        if (
            $profile->status === 'verified'
            && $profile->identity_verification_status === 'verified'
        ) {
            abort(409, 'Your identity verification is already complete.');
        }

        Log::info('Sumsub: Starting verification.', [
            'user_id' => $user->id,
            'level_name' => config('services.sumsub.level_name'),
        ]);

        try {
            $result = $this->sumsub->createAccessToken($user);

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
     * Process final Sumsub applicant review events.
     *
     * Sumsub controls the identity decision. This method never accepts
     * an identity decision or rejection reason from the applicant/admin.
     */
    public function processSumsubWebhook(array $payload): void
    {
        $eventType = $payload['type'] ?? 'unknown';

        if ($eventType !== 'applicantReviewed') {
            Log::info('Sumsub: Event ignored.', [
                'event_type' => $eventType,
            ]);

            return;
        }

        $applicant = $payload['applicant'] ?? [];

        $externalUserId = $payload['externalUserId']
            ?? $applicant['externalUserId']
            ?? null;

        $applicantId = $payload['applicantId']
            ?? $applicant['id']
            ?? null;

        $reviewStatus = $payload['reviewStatus'] ?? null;

        $reviewAnswer = $payload['reviewResult']['reviewAnswer']
            ?? $payload['reviewAnswer']
            ?? null;

        if (! $externalUserId) {
            Log::warning('Sumsub: Missing external user ID.', [
                'applicant_id' => $applicantId,
            ]);

            return;
        }

        // SumsubVerificationService sends the Laravel user ID as userId.
        $user = User::query()->find($externalUserId);

        if (! $user) {
            Log::warning('Sumsub: User not found for external user ID.', [
                'external_user_id' => $externalUserId,
                'applicant_id' => $applicantId,
            ]);

            return;
        }

        if ($reviewStatus !== 'completed') {
            Log::info('Sumsub: Review is not final.', [
                'user_id' => $user->id,
                'review_status' => $reviewStatus,
            ]);

            if ($applicantId) {
                $this->profiles->updateForUser($user, [
                    'sumsub_applicant_id' => $applicantId,
                ]);
            }

            return;
        }

        $notification = DB::transaction(function () use (
            $user,
            $applicantId,
            $reviewAnswer,
            $payload
        ): ?array {
            $profile = $this->profiles->firstOrCreateForUser($user);

            if ($applicantId) {
                $profile->sumsub_applicant_id = $applicantId;
            }

            if ($reviewAnswer === 'GREEN') {
                $profile->identity_verification_status = 'verified';
                $profile->identity_verified_at = now();
                $profile->rejection_reason = null;

                $profile->status = match ($profile->bank_verification_status) {
                    'verified', 'approved' => 'verified',
                    'rejected', 'failed' => 'rejected',
                    default => 'pending',
                };

                $user->forceFill([
                    'kyc_status' => $profile->status,
                ])->save();

                $profile->save();

                Log::info('Sumsub: Identity review passed.', [
                    'user_id' => $user->id,
                    'applicant_id' => $applicantId,
                    'bank_status' => $profile->bank_verification_status,
                    'overall_status' => $profile->status,
                ]);

                return null;
            }

            if ($reviewAnswer !== 'RED') {
                Log::warning('Sumsub: Unknown final review answer.', [
                    'user_id' => $user->id,
                    'review_answer' => $reviewAnswer,
                ]);

                if ($applicantId) {
                    $profile->save();
                }

                return null;
            }

            $reviewResult = $payload['reviewResult'] ?? [];

            $reason = $reviewResult['clientComment']
                ?? $reviewResult['moderationComment']
                ?? null;

            // Some Sumsub responses provide structured rejection labels.
            if (! is_string($reason) || trim($reason) === '') {
                $labels = $reviewResult['rejectLabels'] ?? [];

                if (is_array($labels) && $labels !== []) {
                    $reason = collect($labels)
                        ->map(function ($label) {
                            if (is_string($label)) {
                                return $label;
                            }

                            if (is_array($label)) {
                                return $label['name']
                                    ?? $label['description']
                                    ?? $label['label']
                                    ?? null;
                            }

                            return null;
                        })
                        ->filter()
                        ->unique()
                        ->implode(', ');
                }
            }

            $reason = is_string($reason) && trim($reason) !== ''
                ? trim($reason)
                : 'Sumsub rejected the identity verification. Please review the information and try again.';

            $wasAlreadyRejected = $profile->identity_verification_status === 'rejected'
                && $profile->rejection_reason === $reason;

            $profile->identity_verification_status = 'rejected';
            $profile->status = 'rejected';
            $profile->rejection_reason = $reason;

            $user->forceFill([
                'kyc_status' => 'rejected',
            ])->save();

            $profile->save();

            Log::warning('Sumsub: Identity review rejected.', [
                'user_id' => $user->id,
                'applicant_id' => $applicantId,
                'reason' => $reason,
            ]);

            if ($wasAlreadyRejected) {
                return null;
            }

            return [
                'email' => $user->email,
                'name' => $user->first_name ?? 'Investor',
                'reason' => $reason,
                'user_id' => $user->id,
            ];
        });

        // Send the notification after the database transaction commits.
        if ($notification && ! empty($notification['email'])) {
            try {
                Mail::raw(
                    "Hello {$notification['name']},\n\n"
                    . "Sumsub was unable to verify your identity.\n\n"
                    . "Reason provided by the verification process:\n"
                    . $notification['reason']
                    . "\n\nPlease sign in to your account to review your KYC status and follow the next steps.\n\n"
                    . "FadaWebs",
                    function ($message) use ($notification) {
                        $message
                            ->to($notification['email'])
                            ->subject('Your identity verification result');
                    }
                );
            } catch (\Throwable $exception) {
                // Email delivery failure must not undo Sumsub's decision.
                Log::error('KYC: Rejection email could not be sent.', [
                    'user_id' => $notification['user_id'],
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
