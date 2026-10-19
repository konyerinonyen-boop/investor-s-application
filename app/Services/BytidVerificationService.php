<?php

namespace App\Services;

use App\Models\KycProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BytidVerificationService
{
    private const MAX_IMAGE_SIZE = 8 * 1024 * 1024;

    /**
     * Create the authenticated Bytid HTTP client.
     */
    protected function client()
    {
        $key = config('services.bytid.api_key');
        $baseUrl = config('services.bytid.base_url');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException(
                'Bytid API key is not configured.'
            );
        }

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new RuntimeException(
                'Bytid API base URL is not configured.'
            );
        }

        return Http::withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->baseUrl(rtrim($baseUrl, '/'));
    }

    /**
     * Send a POST request and return Bytid's data object.
     */
    protected function post(
        string $endpoint,
        array $payload
    ): array {
        $response = $this->client()->post($endpoint, $payload);

        return $this->parseResponse(
            $response,
            'Bytid verification request failed.'
        );
    }

    /**
     * Send a GET request and return Bytid's data object.
     */
    protected function get(string $endpoint): array
    {
        $response = $this->client()->get($endpoint);

        return $this->parseResponse(
            $response,
            'Could not retrieve the Bytid verification result.'
        );
    }

    /**
     * Validate Bytid's HTTP response.
     */
    protected function parseResponse(
        $response,
        string $defaultMessage
    ): array {
        $result = $response->json();

        if (
            ! $response->successful() ||
            ! is_array($result) ||
            ($result['success'] ?? false) !== true
        ) {
            $message = data_get(
                $result,
                'error.message',
                $defaultMessage
            );

            throw new RuntimeException(
                is_string($message) ? $message : $defaultMessage
            );
        }

        $data = $result['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException(
                'Bytid returned an invalid response.'
            );
        }

        return $data;
    }

    /**
     * Map the application's document types to Bytid document types.
     */
    protected function mapDocumentType(string $documentType): string
    {
        $types = [
            'national_id' => 'nin_slip',
            'nin_slip' => 'nin_slip',
            'nin_card' => 'nin_card',
            'passport' => 'passport',
            'drivers_license' => 'drivers_license',
            'voters_card' => 'voters_card',
        ];

        $mapped = $types[$documentType] ?? null;

        if ($mapped === null) {
            throw new RuntimeException(
                'Unsupported identity document type.'
            );
        }

        return $mapped;
    }

    /**
     * Submit a stored identity document to Bytid.
     *
     * This method submits the document but does not mark the
     * customer's identity as verified.
     *
     * Pass the same $reference when retrying the same submission
     * after a timeout. Store it in your database for reliable retries.
     *
     * Pass $livenessSessionId when the document photo must be
     * matched against a face verified through Bytid liveness.
     */
    public function submitStoredDocument(
        int $userId,
        string $documentType,
        string $frontPath,
        ?string $backPath = null,
        ?string $livenessSessionId = null,
        ?string $reference = null
    ): array {
        $bytidDocumentType = $this->mapDocumentType($documentType);

        $front = $this->readImage($frontPath);

        $reference ??= 'kyc-user-' . $userId . '-document-' .
            Str::uuid()->toString();

        $payload = [
            'front_image' => base64_encode($front),
            'document_type' => $bytidDocumentType,
            'verify_with_registry' => $bytidDocumentType !== 'passport',
            'reference' => $reference,
        ];

        if ($backPath !== null && trim($backPath) !== '') {
            $payload['back_image'] = base64_encode(
                $this->readImage($backPath)
            );
        }

        if (
            $livenessSessionId !== null &&
            trim($livenessSessionId) !== ''
        ) {
            $payload['liveness_session_id'] = $livenessSessionId;
        }

        $result = $this->post('/documents/verify', $payload);

        return [
            ...$result,
            'reference' => $result['reference'] ?? $reference,
        ];
    }

    /**
     * Create a Bytid liveness session.
     *
     * The returned verification_url should be opened by the user
     * to complete the liveness process.
     */
    public function startLiveness(KycProfile $profile): array
    {
        $payload = [
            'reference' => 'kyc-' . $profile->id . '-live-' .
                Str::uuid()->toString(),
        ];

        $redirectUrl = config('services.bytid.redirect_url');

        if (is_string($redirectUrl) && trim($redirectUrl) !== '') {
            $payload['redirect_url'] = $redirectUrl;
        }

        $result = $this->post('/liveness/sessions', $payload);

        if (empty($result['id']) || empty($result['url'])) {
            throw new RuntimeException(
                'Bytid did not return a liveness session ID and URL.'
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

    /**
     * Retrieve the status of a liveness session.
     */
    public function getLivenessSession(string $sessionId): array
    {
        return $this->get(
            '/liveness/sessions/' . rawurlencode($sessionId)
        );
    }

    /**
     * Verify a KYC profile's stored document against a completed
     * Bytid liveness session.
     *
     * The profile is marked verified only when Bytid reports
     * verified and the required checks pass.
     */
    public function verifyDocument(
        KycProfile $profile,
        string $sessionId
    ): array {
        if (trim($sessionId) === '') {
            throw new RuntimeException(
                'A valid Bytid liveness session ID is required.'
            );
        }

        if (empty($profile->id_document_path_front)) {
            throw new RuntimeException(
                'The front identity document path is missing.'
            );
        }

        $documentType = $this->mapDocumentType(
            (string) $profile->id_document_type
        );

        $reference = 'kyc-' . $profile->id . '-document-' .
            Str::uuid()->toString();

        $result = $this->submitStoredDocument(
            userId: (int) $profile->user_id,
            documentType: $documentType,
            frontPath: $profile->id_document_path_front,
            backPath: $profile->id_document_path_back,
            livenessSessionId: $sessionId,
            reference: $reference,
        );

        $providerStatus = $result['status'] ?? null;

        $faceMatchStatus = data_get(
            $result,
            'checks.face_match.status'
        );

        $documentTypeMatched = data_get(
            $result,
            'checks.document_type_matches'
        ) === true;

        $registryStatus = data_get(
            $result,
            'checks.registry.status'
        );

        $registryPassed = $documentType === 'passport'
            || $registryStatus === 'verified';

        $identityStatus = match (true) {
            $providerStatus === 'rejected',
            $faceMatchStatus === 'not_matched'
                => 'rejected',

            $providerStatus === 'verified'
                && $faceMatchStatus === 'matched'
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

    /**
     * Retrieve a document verification result from Bytid.
     */
    public function getDocumentVerification(string $verificationId): array
    {
        if (trim($verificationId) === '') {
            throw new RuntimeException(
                'A Bytid document verification ID is required.'
            );
        }

        return $this->get(
            '/documents/' . rawurlencode($verificationId)
        );
    }

    /**
     * Read an image from private local storage.
     *
     * Public storage is supported temporarily for older uploads.
     */
    protected function readImage(string $path): string
    {
        if (trim($path) === '') {
            throw new RuntimeException(
                'An identity document image path is required.'
            );
        }

        $disk = null;

        if (Storage::disk('local')->exists($path)) {
            $disk = Storage::disk('local');
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = Storage::disk('public');
        }

        if ($disk === null) {
            throw new RuntimeException(
                'A required KYC image could not be found.'
            );
        }

        if ($disk->size($path) > self::MAX_IMAGE_SIZE) {
            throw new RuntimeException(
                'The identity document image must not exceed 8 MB.'
            );
        }

        $mimeType = $disk->mimeType($path);

        if (! in_array(
            $mimeType,
            ['image/jpeg', 'image/png', 'image/webp'],
            true
        )) {
            throw new RuntimeException(
                'The identity document must be a JPEG, PNG, or WEBP image.'
            );
        }

        $contents = $disk->get($path);

        if ($contents === '') {
            throw new RuntimeException(
                'The identity document image is empty.'
            );
        }

        return $contents;
    }
}