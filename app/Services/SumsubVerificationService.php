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
    
public function verifyWebhook(
    string $rawBody,
    ?string $providedDigest,
    ?string $algorithmHeader,
): ?array {
    $secret = config('services.sumsub.webhook_secret');

    if (! $secret || ! $providedDigest) {
        return null;
    }

    $algorithms = [
        'HMAC_SHA256' => 'sha256',
        'HMAC_SHA512' => 'sha512',
        'HMAC_SHA1' => 'sha1',
    ];

    $algorithm = strtoupper($algorithmHeader ?? 'HMAC_SHA256');

    if (! isset($algorithms[$algorithm])) {
        return null;
    }

    $expected = hash_hmac(
        $algorithms[$algorithm],
        $rawBody,
        $secret
    );

    if (! hash_equals($expected, $providedDigest)) {
        return null;
    }

    $payload = json_decode($rawBody, true);

    return is_array($payload) ? $payload : null;
}

}
