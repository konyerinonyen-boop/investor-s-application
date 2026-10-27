<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SumsubVerificationService
{
    /**
     * Generate a short-lived Sumsub WebSDK access token.
     */
    public function createAccessToken(User $user): array
    {
        $path = '/resources/accessTokens/sdk';

        $appToken = config('services.sumsub.app_token');
        $secretKey = config('services.sumsub.secret_key');
        $levelName = config('services.sumsub.level_name');

        if (! $appToken || ! $secretKey || ! $levelName) {
            throw new RuntimeException(
                'Sumsub is not configured. Check your SUMSUB environment variables.'
            );
        }

        $body = json_encode([
            'userId' => (string) $user->id,
            'levelName' => $levelName,
            'ttlInSecs' => 600,
            'applicantIdentifiers' => array_filter([
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ]),
        ], JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new RuntimeException(
                'Could not prepare the Sumsub request.'
            );
        }

        $timestamp = (string) time();

        $signature = hash_hmac(
            'sha256',
            $timestamp . 'POST' . $path . $body,
            $secretKey
        );

        $response = Http::baseUrl(
            rtrim(
                config(
                    'services.sumsub.base_url',
                    'https://api.sumsub.com'
                ),
                '/'
            )
        )
            ->acceptJson()
            ->withHeaders([
                'X-App-Token' => $appToken,
                'X-App-Access-Ts' => $timestamp,
                'X-App-Access-Sig' => $signature,
            ])
            ->withBody($body, 'application/json')
            ->timeout(30)
            ->post($path);

        if (! $response->successful()) {
            // Do not expose credentials or signatures to the client.
            throw new RuntimeException(
                'Sumsub access-token request failed with HTTP ' .
                $response->status() . '.'
            );
        }

        $result = $response->json();

        if (! is_array($result) || empty($result['token'])) {
            throw new RuntimeException(
                'Sumsub returned an invalid access-token response.'
            );
        }

        return [
            'token' => $result['token'],
            'expires_in' => 600,
        ];
    }
    

public function verifyWebhook(string $rawBody, ?string $signature, ?string $algorithm): ?array
{
    $secret = config('services.sumsub.webhook_secret');

    if (empty($secret) || empty($signature) || empty($algorithm)) {
        \Illuminate\Support\Facades\Log::warning(
            'Sumsub: Missing webhook signature configuration.',
            [
                'has_secret' => ! empty($secret),
                'has_signature' => ! empty($signature),
                'algorithm' => $algorithm,
            ]
        );

        return null;
    }

    // Sumsub may send HMAC_SHA256_HEX instead of HMAC_SHA256.
    $normalizedAlgorithm = strtoupper($algorithm);

    $hashAlgorithm = match ($normalizedAlgorithm) {
        'HMAC_SHA256', 'HMAC_SHA256_HEX' => 'sha256',
        'HMAC_SHA512', 'HMAC_SHA512_HEX' => 'sha512',
        'HMAC_SHA1', 'HMAC_SHA1_HEX' => 'sha1',
        default => null,
    };

    if ($hashAlgorithm === null) {
        \Illuminate\Support\Facades\Log::warning(
            'Sumsub: Unsupported webhook signature algorithm.',
            ['algorithm' => $algorithm]
        );

        return null;
    }

    // hash_hmac() returns a hexadecimal digest by default.
    $expectedSignature = hash_hmac(
        $hashAlgorithm,
        $rawBody,
        $secret
    );

    if (! hash_equals($expectedSignature, trim($signature))) {
        \Illuminate\Support\Facades\Log::warning(
            'Sumsub: Webhook signature mismatch.',
            ['algorithm' => $algorithm]
        );

        return null;
    }

    $payload = json_decode($rawBody, true);

    if (! is_array($payload)) {
        \Illuminate\Support\Facades\Log::warning(
            'Sumsub: Webhook payload is invalid JSON.'
        );

        return null;
    }

    return $payload;
}


}
