<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DojahService
{
    /**
     * Build an authenticated Dojah API request.
     *
     * Dojah expects its secret key directly in the
     * Authorization header, without the Bearer prefix.
     */
    private function client()
    {
        $appId = config('services.dojah.app_id');
        $secretKey = config('services.dojah.secret_key');
        $baseUrl = rtrim(
            (string) config('services.dojah.base_url'),
            '/'
        );

        if (
            ! is_string($appId) ||
            trim($appId) === '' ||
            ! is_string($secretKey) ||
            trim($secretKey) === '' ||
            $baseUrl === ''
        ) {
            throw new RuntimeException(
                'Dojah API credentials are not configured.'
            );
        }

        return Http::acceptJson()
            ->withHeaders([
                'AppId' => $appId,
                'Authorization' => $secretKey,
            ])
            ->connectTimeout(10)
            ->timeout(30)
            ->baseUrl($baseUrl);
    }

    /**
     * Validate a BVN against the investor's name and date of birth.
     *
     * Expected data:
     * bvn, first_name, last_name, dob (YYYY-MM-DD)
     */
    public function validateBvn(array $data): array
    {
        $response = $this->client()->get(
            '/api/v1/kyc/bvn',
            [
                'bvn' => $data['bvn'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'dob' => $data['dob'] ?? null,
            ]
        );

        return $this->checkedResponse($response->json(), $response->status());
    }

    /**
     * Verify a BVN against a selfie.
     *
     * $selfieBase64 must contain the encoded image data,
     * without a data:image/... prefix.
     */
    public function verifyBvnSelfie(
        string $bvn,
        string $selfieBase64
    ): array {
        $response = $this->client()
            ->asJson()
            ->post('/api/v1/kyc/bvn/verify', [
                'bvn' => $bvn,
                'selfie_image' => $selfieBase64,
                'threshold' => (int) config(
                    'services.dojah.selfie_threshold',
                    90
                ),
            ]);

        return $this->checkedResponse($response->json(), $response->status());
    }

    /**
     * Verify an NIN against a selfie.
     */
    public function verifyNinSelfie(
        string $nin,
        string $selfieBase64
    ): array {
        $response = $this->client()
            ->asJson()
            ->post('/api/v1/kyc/nin/verify', [
                'nin' => $nin,
                'selfie_image' => $selfieBase64,
                'threshold' => (int) config(
                    'services.dojah.selfie_threshold',
                    90
                ),
            ]);

        return $this->checkedResponse($response->json(), $response->status());
    }

    /**
     * Resolve a Nigerian bank account to its registered name.
     */
    public function resolveBankAccount(
        string $accountNumber,
        string $bankCode
    ): array {
        $response = $this->client()->get(
            '/api/v1/general/account',
            [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]
        );

        return $this->checkedResponse($response->json(), $response->status());
    }

    /**
     * Reject HTTP errors and malformed provider responses.
     *
     * This does not approve KYC. The KYC workflow must inspect
     * the returned entity and make its own explicit decision.
     */
    private function checkedResponse(
        mixed $body,
        int $status
    ): array {
        if (
            $status < 200 ||
            $status >= 300 ||
            ! is_array($body) ||
            ! isset($body['entity']) ||
            ! is_array($body['entity'])
        ) {
            throw new RuntimeException(
                'Dojah verification failed or returned an invalid response.'
            );
        }

        return $body;
    }
}
