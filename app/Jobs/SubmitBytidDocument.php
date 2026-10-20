<?php

namespace App\Jobs;

use App\Models\KycProfile;
use App\Services\BytidVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SubmitBytidDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 90, 180];

    public function __construct(
        public int $kycProfileId,
        public string $livenessSessionId
    ) {
    }

    public function handle(BytidVerificationService $bytid): void
    {
        $profile = KycProfile::query()
            ->findOrFail($this->kycProfileId);

        // Do not submit if this is no longer the active liveness session.
        if (
            $profile->selfie_verification_reference
            !== $this->livenessSessionId
        ) {
            return;
        }

        // A previous attempt may already have submitted the document.
        if (filled($profile->bytid_document_verification_id)) {
                return;
            }

        if (! filled($profile->identity_verification_reference)) {
            throw new RuntimeException(
                'The KYC document reference has not been saved.'
            );
        }

        // Verify the authoritative liveness status before submission.
        $session = $bytid->getLivenessSession(
            $this->livenessSessionId
        );

        $status = strtolower(
            (string) ($session['status'] ?? '')
        );

        if ($status !== 'passed') {
            throw new RuntimeException(
                'Bytid liveness verification has not passed.'
            );
        }

        // This method must reuse the saved reference, not generate a new one.
        $bytid->verifyDocument(
            $profile->fresh(),
            $this->livenessSessionId
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Bytid document submission job failed.', [
            'kyc_profile_id' => $this->kycProfileId,
            'exception_class' => $exception::class,
        ]);

        $profile = KycProfile::query()
            ->find($this->kycProfileId);

        if (! $profile) {
            return;
        }

        // Keep the overall KYC status pending. An API error is not
        // evidence that the customer's identity document was rejected.
        if (
            ! filled($profile->bytid_document_verification_id)
            && $profile->identity_verification_status === 'pending'
        ) {
            $profile->forceFill([
                'identity_verification_status' => 'submission_failed',
            ])->save();
        }
    }
}
