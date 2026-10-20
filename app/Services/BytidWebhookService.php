
<?php

namespace App\Services;

use App\Jobs\SubmitBytidDocument;
use App\Models\BytidWebhookEvent;
use App\Models\KycProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BytidWebhookService
{
    public function process(
        string $eventId,
        string $eventType,
        array $payload
    ): void {
        DB::transaction(function () use (
            $eventId,
            $eventType,
            $payload
        ): void {
            $event = BytidWebhookEvent::query()
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            // This event has already been processed successfully.
            if ($event?->processed_at !== null) {
                return;
            }

            if (! $event) {
                try {
                    $event = BytidWebhookEvent::query()->create([
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]);
                } catch (QueryException $exception) {
                    // A concurrent delivery may have inserted this event.
                    // Throwing causes the controller to return 500 so
                    // the provider can retry.
                    throw $exception;
                }
            }

            $data = is_array($payload['data'] ?? null)
                ? $payload['data']
                : [];

            switch ($eventType) {
                case 'liveness.completed':
                    $this->handleLivenessCompleted($data);
                    break;

                case 'document.completed':
                case 'kyc.completed':
                case 'verification.completed':
                    $this->handleDocumentCompleted($data);
                    break;

                // Valid events that this KYC workflow does not use.
                case 'webhook.test':
                case 'address.completed':
                case 'aml.match_found':
                    break;

                default:
                    // Unknown signed events are recorded but do not change KYC.
                    break;
            }

            $event->forceFill([
                'event_type' => $eventType,
                'processed_at' => now(),
            ])->save();
        }, attempts: 3);
    }

    private function handleLivenessCompleted(array $data): void
    {
        $sessionId = $data['id'] ?? $data['session_id'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw new RuntimeException(
                'Bytid liveness event has no session ID.'
            );
        }

        $profile = KycProfile::query()
            ->where('selfie_verification_reference', $sessionId)
            ->lockForUpdate()
            ->first();

        // Do not guess which profile an unmatched event belongs to.
        if (! $profile) {
            return;
        }

        $status = Str::lower(
            (string) ($data['status'] ?? 'pending')
        );

        if (in_array(
            $status,
            ['passed', 'verified', 'completed'],
            true
        )) {
            $profile->forceFill([
                'selfie_verification_status' => 'verified',
                'selfie_verified_at' => now(),
            ])->save();

            // The queued job runs after the transaction commits.
            // It confirms the authoritative liveness status with Bytid
            // before submitting the document.
            SubmitBytidDocument::dispatch(
                (int) $profile->id,
                $sessionId
            )->afterCommit();

            return;
        }

        if (in_array(
            $status,
            ['failed', 'rejected', 'expired'],
            true
        )) {
            $profile->forceFill([
                'selfie_verification_status' => 'failed',
                'selfie_verified_at' => null,
            ])->save();
        }
    }

    private function handleDocumentCompleted(array $data): void
    {
        $reference = $data['reference']
            ?? data_get($data, 'metadata.reference');

        if (! is_string($reference) || $reference === '') {
            throw new RuntimeException(
                'Bytid document event has no verification reference.'
            );
        }

        $profile = KycProfile::query()
            ->where('identity_verification_reference', $reference)
            ->lockForUpdate()
            ->first();

        // It may belong to an old attempt. Never guess a profile.
        if (! $profile) {
            return;
        }

        $status = Str::lower(
            (string) ($data['status'] ?? 'review')
        );

        $faceStatus = Str::lower(
            (string) data_get(
                $data,
                'checks.face_match.status',
                'unknown'
            )
        );

        $typeMatches = data_get(
            $data,
            'checks.document_type_matches'
        ) === true;

        $registryStatus = Str::lower(
            (string) data_get(
                $data,
                'checks.registry.status',
                'unknown'
            )
        );

        $isPassport = $profile->id_document_type === 'passport';

        $registryPassed = $isPassport
            || $registryStatus === 'verified';

        $identityStatus = match (true) {
            $status === 'rejected',
            $faceStatus === 'not_matched'
                => 'rejected',

            $status === 'verified'
                && $faceStatus === 'matched'
                && $typeMatches
                && $registryPassed
                => 'verified',

            $status === 'review'
                => 'review',

            default => 'pending',
        };

        $verificationId = $data['id']
            ?? $data['verification_id']
            ?? null;

        $profile->forceFill([
            'bytid_document_verification_id' =>
                is_string($verificationId) ? $verificationId : null,

            'identity_verification_status' => $identityStatus,

            'identity_verified_at' => $identityStatus === 'verified'
                ? now()
                : null,

            // This is still awaiting your application's admin review.
            'status' => 'pending',

            'rejection_reason' => $identityStatus === 'rejected'
                ? 'Bytid rejected the identity document.'
                : null,
        ])->save();

        // Do not approve or downgrade the user's overall KYC status here.
        // Provider verification is evidence for your existing review flow.
    }
}
