<?php

namespace App\Services;

use App\Models\BytidWebhookEvent;
use App\Models\KycProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BytidWebhookService
{
    public function process(string $eventId, string $eventType, array $payload): void
    {
        DB::transaction(function () use ($eventId, $eventType, $payload): void {
            // Unique DB constraint handles concurrent duplicate deliveries.
            $event = BytidWebhookEvent::query()->where('event_id', $eventId)->lockForUpdate()->first();
            if ($event?->processed_at !== null) {
                return;
            }

            if (! $event) {
                try {
                    $event = BytidWebhookEvent::query()->create([
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                    // A concurrent request inserted this event. Throw to let the provider retry.
                    throw $exception;
                }
            }

            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            switch ($eventType) {
                case 'liveness.completed':
                    $this->handleLivenessCompleted($data);
                    break;
                case 'document.completed':
                    $this->handleDocumentCompleted($data);
                    break;
                case 'kyc.completed':
                case 'verification.completed':
                    // Event envelopes differ by product. Only apply when a documented reference is present.
                    $this->handleDocumentCompleted($data);
                    break;
                case 'webhook.test':
                case 'address.completed':
                case 'aml.match_found':
                    // Acknowledge valid events not currently used by this application's KYC flow.
                    break;
                default:
                    // Unknown signed event: record it to avoid endless retries; no KYC changes.
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
            throw new RuntimeException('Bytid liveness event has no session ID.');
        }

        $profile = KycProfile::query()
            ->where('selfie_verification_reference', $sessionId)
            ->lockForUpdate()
            ->first();

        // Unmatched events may belong to an old attempt. Do not guess a profile.
        if (! $profile) {
            return;
        }

        $status = Str::lower((string) ($data['status'] ?? 'pending'));

        if (in_array($status, ['passed', 'verified', 'completed'], true)) {
            $profile->forceFill([
                'selfie_verification_status' => 'verified',
                'selfie_verified_at' => now(),
            ])->save();

            // Confirm authoritative session status before submitting document verification.
            $session = app(BytidVerificationService::class)->getLivenessSession($sessionId);
            if (($session['status'] ?? null) === 'passed') {
                app(BytidVerificationService::class)->verifyDocument($profile->fresh(), $sessionId);
            }

            return;
        }

        if (in_array($status, ['failed', 'rejected', 'expired'], true)) {
            // Failed attempt can be retried; do not reject the whole KYC application.
            $profile->forceFill([
                'selfie_verification_status' => 'failed',
                'selfie_verified_at' => null,
            ])->save();
        }
    }

    private function handleDocumentCompleted(array $data): void
    {
        $reference = $data['reference'] ?? data_get($data, 'metadata.reference');
        if (! is_string($reference) || $reference === '') {
            // Payload needs a documented correlatable ID/reference; never infer the user.
            throw new RuntimeException('Bytid document event has no verification reference.');
        }

        $profile = KycProfile::query()
            ->where('identity_verification_reference', $reference)
            ->lockForUpdate()
            ->first();

        if (! $profile) {
            // Reference may be from an old attempt; do not mutate another profile.
            return;
        }

        $status = Str::lower((string) ($data['status'] ?? 'review'));
        $faceStatus = Str::lower((string) data_get($data, 'checks.face_match.status', 'unknown'));
        $typeMatches = data_get($data, 'checks.document_type_matches') === true;
        $registryStatus = Str::lower((string) data_get($data, 'checks.registry.status', 'unknown'));
        $registryPassed = $profile->id_document_type === 'passport' || $registryStatus === 'verified';

        $identityStatus = match (true) {
            $status === 'rejected', $faceStatus === 'not_matched' => 'rejected',
            $status === 'verified' && $faceStatus === 'matched' && $typeMatches && $registryPassed => 'verified',
            default => 'pending',
        };

        // Do not promote the overall KYC status from a provider event.
        $profile->forceFill([
            'identity_verification_status' => $identityStatus,
            'identity_verified_at' => $identityStatus === 'verified' ? now() : null,
            'status' => 'pending',
            'rejection_reason' => $identityStatus === 'rejected'
                ? 'Bytid rejected the identity document.'
                : null,
        ])->save();

        $profile->user()->update(['kyc_status' => 'pending']);
    }
}
