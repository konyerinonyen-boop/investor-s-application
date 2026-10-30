<?php

namespace App\Services;

use App\Interfaces\PaymantGateWayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class KorapayService implements PaymantGateWayInterface
{
    private function client()
    {
        $secret = config('services.korapay.secret_key');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException(
                'Korapay secret key is not configured.'
            );
        }

        return Http::acceptJson()
            ->withToken($secret)
            ->timeout(30);
    }

    /**
     * Start a Korapay checkout for a wallet deposit.
     * Korapay expects whole-naira NGN amounts for checkout.
     */
    public function initialize(array $data): array
    {
        $email = $data['email'] ?? null;
        $name = $data['full_name'] ?? $data['name'] ?? null;
        $amount = $data['amount'] ?? null;

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'A valid customer email is required for Korapay.'
            );
        }

        if (
            ! is_numeric($amount) ||
            (float) $amount <= 0 ||
            floor((float) $amount) !== (float) $amount
        ) {
            throw new RuntimeException(
                'Korapay checkout requires a positive whole-naira amount.'
            );
        }

        $reference = $data['reference']
            ?? 'KORA-' . Str::upper(Str::random(24));

        $payload = [
            'amount' => (int) $amount,
            'currency' => 'NGN',
            'reference' => $reference,
            'customer' => [
                'email' => $email,
            ],
            'narration' => 'FadaWebs wallet deposit',
            'metadata' => [
                'payment_reference' => $reference,
            ],
        ];

        if (is_string($name) && trim($name) !== '') {
            $payload['customer']['name'] = trim($name);
        }

        $response = $this->client()->post(
            'https://api.korapay.com/merchant/api/v1/charges/initialize',
            $payload
        )->throw();

        $result = $response->json();

        if (
            ! is_array($result) ||
            ($result['status'] ?? false) !== true ||
            empty($result['data']['reference']) ||
            empty($result['data']['checkout_url'])
        ) {
            throw new RuntimeException(
                'Korapay did not return a valid checkout session.'
            );
        }

        return $result;
    }

    /**
     * Verify a checkout payment directly with Korapay.
     */
    public function verify(string $reference): array
    {
        if (trim($reference) === '') {
            throw new RuntimeException(
                'A Korapay transaction reference is required.'
            );
        }

        $response = $this->client()->get(
            'https://api.korapay.com/merchant/api/v1/charges/'
            . rawurlencode($reference)
        )->throw();

        $result = $response->json();

        if (
            ! is_array($result) ||
            ($result['status'] ?? false) !== true ||
            ! isset($result['data'])
        ) {
            throw new RuntimeException(
                'Korapay could not verify this transaction.'
            );
        }

        return $result;
    }

    /**
     * Send a bank payout through Korapay.
     *
     * Amount is expressed in naira, e.g. "15000.00".
     * The caller must save the withdrawal and unique reference first.
     */
    public function disburse(array $data): array
    {
        $amount = $data['amount'] ?? null;
        $email = $data['email'] ?? null;
        $name = $data['full_name'] ?? $data['name'] ?? null;
        $bankCode = $data['bank_code'] ?? null;
        $accountNumber = $data['account_number'] ?? null;
        $reference = $data['reference'] ?? null;

        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new RuntimeException(
                'A valid positive payout amount is required.'
            );
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'A valid customer email is required for the payout.'
            );
        }

        if (
            ! is_string($bankCode) || trim($bankCode) === '' ||
            ! is_string($accountNumber) ||
            ! preg_match('/^\d{10}$/', $accountNumber)
        ) {
            throw new RuntimeException(
                'A valid bank code and 10-digit account number are required.'
            );
        }

        if (! is_string($reference) || strlen($reference) < 5) {
            throw new RuntimeException(
                'A unique payout reference of at least 5 characters is required.'
            );
        }

        $payload = [
            'reference' => $reference,
            'destination' => [
                'type' => 'bank_account',
                'amount' => number_format((float) $amount, 2, '.', ''),
                'currency' => 'NGN',
                'bank_account' => [
                    'bank' => $bankCode,
                    'account' => $accountNumber,
                ],
                'customer' => [
                    'email' => $email,
                    'name' => is_string($name) && trim($name) !== ''
                        ? trim($name)
                        : $email,
                ],
            ],
        ];

        return $this->client()->post(
            'https://api.korapay.com/merchant/api/v1/transactions/disburse',
            $payload
        )->throw()->json();
    }

    /**
     * Query a payout after a response timeout or uncertain result.
     * Do not retry the disbursement until this result is checked.
     */
    public function verifyDisbursement(string $reference): array
    {
        if (trim($reference) === '') {
            throw new RuntimeException(
                'A payout reference is required for verification.'
            );
        }

        return $this->client()->get(
            'https://api.korapay.com/merchant/api/v1/transactions/'
            . rawurlencode($reference)
        )->throw()->json();
    }
}
