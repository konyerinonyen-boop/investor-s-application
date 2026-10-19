<?php

namespace App\Services;

use App\Models\KycProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BytidVerificationService
{
    protected function client()
    {
        $key = config('services.bytid.api_key');

        if (! $key) {
            throw new RuntimeException(
                'Bytid API key is not configured.'
            );
        }

        return Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->baseUrl(
                rtrim(config('services.bytid.base_url'), '/')
            );
    }

    protected function post(string $endpoint, array $payload): array
    {
        $response = $this->client()->post($endpoint, $payload);
        $result = $response->json();

        if (
            ! $response->successful() ||
            ! is_array($result) ||
            ($result['success'] ?? false) !== true
        ) {
            $message = data_get(
                $result,
                'error.message',
                'Bytid verification request failed.'
            );

            throw new RuntimeException($message);
        }

        return $result['data'] ?? [];
    }

    protected function get(string $endpoint): array
    {
        $response = $this->client()->get($endpoint);
        $result = $response->json();

        if (
            ! $response->successful() ||
            ! is_array($result) ||
            ($result['success'] ?? false) !== true
        ) {
            $message = data_get(
                $result,
                'error.message',
                'Could not retrieve the Bytid verification result.'
            );

            throw new RuntimeException($message);
        }

        return $result['data'] ?? [];
    }

    public function startLiveness(KycProfile $profile): array
    {
        $result = $this->post('/liveness/sessions', [
            'reference' => 'kyc-' . $profile->id . '-live-' .
                bin2hex(random_bytes(8)),
            'redirect_url' => config('services.bytid.redirect_url'),
        ]);

        if (empty($result['id']) || empty($result['url'])) {
            throw new RuntimeException(
                'Bytid did not return a liveness session URL.'
            );
        }

        $profile->forceFill([
            'selfie_verification_status' => 'pending',
            'selfie_verification_reference' => $result['id'],
            'selfie_verified_at' => null,
        ])->save();

        return [
            'session_id' => $result['id'],
            'verification_url' => $result['url'],
            'status' => $result['status'] ?? 'pending',
        ];
    }

    public function getLivenessSession(string $sessionId): array
    {
        return $this->get(
            '/liveness/sessions/' . rawurlencode($sessionId)
        );
    }

    public function verifyDocument(
        KycProfile $profile,
        string $sessionId
    ): array {
        $documentTypes = [
            'national_id' => 'nin_slip',
            'passport' => 'passport',
            'drivers_license' => 'drivers_license',
            'voters_card' => 'voters_card',
        ];

        $documentType = $documentTypes[$profile->id_document_type]
            ?? null;

        if (! $documentType) {
            throw new RuntimeException(
                'Unsupported identity document type.'
            );
        }

        $front = $this->readImage(
            $profile->id_document_path_front
        );

       $reference = 'kyc-' . $profile->id . '-document-' . bin2hex(random_bytes(8));

        $profile->forceFill([
            'identity_verification_reference' => $reference,
            'identity_verification_status' => 'pending',
            'identity_verified_at' => null,
        ])->save();

        $payload = [
            'front_image' => base64_encode($front),
            'document_type' => $documentType,
            'liveness_session_id' => $sessionId,
            'verify_with_registry' => true,
            'reference' => $reference,
        ];

        if ($profile->id_document_path_back) {
            $payload['back_image'] = base64_encode(
                $this->readImage($profile->id_document_path_back)
            );
        }

        $result = $this->post('/documents/verify', $payload);

      
        $providerStatus = $result['status'] ?? null;

        $faceMatched =
            data_get($result, 'checks.face_match.status') === 'matched';

        $documentTypeMatched =
            data_get($result, 'checks.document_type_matches') === true;

        $registryPassed = $documentType === 'passport'
            || data_get($result, 'checks.registry.status') === 'verified';

        $identityStatus = match (true) {
            $providerStatus === 'rejected',
            data_get($result, 'checks.face_match.status') === 'not_matched'
                => 'rejected',

            $providerStatus === 'verified'
                && $faceMatched
                && $documentTypeMatched
                && $registryPassed
                => 'verified',

            default => 'pending',
        };

       $profile->forceFill([
    'identity_verification_status' => $identityStatus,
    'identity_verified_at' => $identityStatus === 'verified'
        ? now()
        : null,
        ])->save();


        return $result;
    }

    protected function readImage(string $path): string
    {
        // New uploads should use the private local disk.
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }

        // Temporary compatibility for files uploaded before
        // private storage was enabled. Migrate these files later.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->get($path);
        }

        throw new RuntimeException(
            'A required KYC image could not be found.'
        );
    }
}
