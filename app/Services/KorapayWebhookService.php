<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\WalletWithdrawal;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KorapayWebhookService
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    public function handle(Request $request, string $provider): void
    {
        if (strtolower($provider) !== 'korapay') {
            throw new RuntimeException('Unsupported webhook provider.');
        }

        $secret = config('services.korapay.secret_key');
        $signature = $request->header('x-korapay-signature');

        if (! $secret || ! $signature) {
            throw new RuntimeException(
                'Korapay webhook signature or secret is missing.'
            );
        }

        $payload = $request->json()->all();
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('Invalid Korapay webhook payload.');
        }

        // Korapay signs only the data object.
        $computedSignature = hash_hmac(
            'sha256',
            json_encode($data),
            $secret
        );

        if (! hash_equals($computedSignature, $signature)) {
            throw new RuntimeException('Invalid Korapay webhook signature.');
        }

        $event = $payload['event'] ?? null;

        match ($event) {
            'charge.success' => $this->handleChargeSuccess($data),
            'charge.failed' => $this->handleChargeFailed($data),
            'transfer.success' => $this->handleTransferSuccess($data),
            'transfer.failed' => $this->handleTransferFailed($data),
            default => null,
        };
    }

    private function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (! $reference || ($data['status'] ?? null) !== 'success') {
            throw new RuntimeException('Invalid successful charge event.');
        }

        DB::transaction(function () use ($reference, $data) {
            $payment = Payment::query()
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->provider !== 'korapay') {
                throw new RuntimeException(
                    'Korapay payment reference was not found.'
                );
            }

            if ($payment->purpose !== 'wallet_deposit') {
                throw new RuntimeException(
                    'This webhook is not for a wallet deposit.'
                );
            }

            if (
                $payment->investment_id !== null ||
                $payment->loan_id !== null ||
                $payment->repayment_schedule_id !== null
            ) {
                throw new RuntimeException(
                    'Wallet deposit has conflicting linked records.'
                );
            }

            $this->validateAmountAndCurrency($payment, $data);

            // A repeated successful webhook must never credit twice.
            if ($payment->status === 'successful') {
                return;
            }

            if ($payment->status !== 'pending') {
                throw new RuntimeException(
                    'Payment is not pending. Manual reconciliation is required.'
                );
            }

            $this->walletService->credit(
                $payment->user,
                (string) $payment->amount,
                'deposit',
                'wallet-deposit-' . $payment->id,
                'Wallet deposit via Korapay',
                $payment,
                [
                    'provider' => 'korapay',
                    'provider_reference' => $reference,
                ]
            );

            $payment->update([
                'status' => 'successful',
                'payload' => $data,
            ]);
        }, 3);
    }

    private function handleChargeFailed(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            throw new RuntimeException('Charge reference is missing.');
        }

        DB::transaction(function () use ($reference, $data) {
            $payment = Payment::query()
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->provider !== 'korapay') {
                throw new RuntimeException(
                    'Korapay payment reference was not found.'
                );
            }

            if ($payment->status === 'successful') {
                throw new RuntimeException(
                    'A successful payment cannot be marked as failed.'
                );
            }

            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'failed',
                    'payload' => $data,
                ]);
            }
        }, 3);
    }

    private function handleTransferSuccess(array $data): void
    {
        $this->settleWithdrawal($data, true);
    }

    private function handleTransferFailed(array $data): void
    {
        $this->settleWithdrawal($data, false);
    }

    private function settleWithdrawal(
        array $data,
        bool $successful
    ): void {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            throw new RuntimeException('Transfer reference is missing.');
        }

        $expectedStatus = $successful ? 'success' : 'failed';

        if (($data['status'] ?? null) !== $expectedStatus) {
            throw new RuntimeException('Transfer status does not match its event.');
        }

        DB::transaction(function () use (
            $reference,
            $data,
            $successful
        ) {
            $withdrawal = WalletWithdrawal::query()
                ->where('transfer_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $withdrawal) {
                throw new RuntimeException(
                    'Withdrawal not found for this Korapay reference.'
                );
            }

            if (
                strtoupper((string) ($data['currency'] ?? '')) !==
                strtoupper((string) $withdrawal->currency)
            ) {
                throw new RuntimeException(
                    'Withdrawal currency does not match.'
                );
            }

            $receivedAmount = BigDecimal::of(
                (string) ($data['amount'] ?? '0')
            );

            $expectedAmount = BigDecimal::of(
                (string) $withdrawal->amount
            );

            if (! $receivedAmount->isEqualTo($expectedAmount)) {
                throw new RuntimeException(
                    'Withdrawal amount does not match.'
                );
            }

            // Repeated delivery of the same event is safe.
            if ($successful && $withdrawal->status === 'paid') {
                return;
            }

            if (! $successful && $withdrawal->status === 'failed') {
                return;
            }

            if ($withdrawal->status !== 'processing') {
                throw new RuntimeException(
                    'Withdrawal is not processing. Reconciliation is required.'
                );
            }

            if ($successful) {
                $withdrawal->update([
                    'status' => 'paid',
                    'provider_response' => $data,
                    'transferred_at' => now(),
                    'failure_reason' => null,
                ]);

                return;
            }

            // A failed transfer must return the previously debited
            // amount to the investor's wallet exactly once.
            $debit = $withdrawal->walletTransaction;

            if (
                ! $debit ||
                $debit->direction !== 'debit' ||
                (int) $debit->wallet->user_id !== (int) $withdrawal->user_id
            ) {
                throw new RuntimeException(
                    'Withdrawal debit transaction is missing or invalid. Manual reconciliation is required.'
                );
            }

            $this->walletService->credit(
                $withdrawal->user,
                (string) $withdrawal->amount,
                'withdrawal_refund',
                'wallet-withdrawal-refund-' . $withdrawal->id,
                'Refund for failed Korapay withdrawal',
                $withdrawal,
                [
                    'provider' => 'korapay',
                    'transfer_reference' => $reference,
                ]
            );

            $withdrawal->update([
                'status' => 'failed',
                'provider_response' => $data,
                'failure_reason' => 'Korapay transfer failed. Amount refunded to wallet.',
            ]);
        }, 3);
    }

    private function validateAmountAndCurrency(
        Payment $payment,
        array $data
    ): void {
        $receivedAmount = BigDecimal::of(
            (string) ($data['amount'] ?? '0')
        );

        $expectedAmount = BigDecimal::of(
            (string) $payment->amount
        );

        if (! $receivedAmount->isEqualTo($expectedAmount)) {
            throw new RuntimeException(
                'Korapay payment amount does not match.'
            );
        }

        if (
            strtoupper((string) ($data['currency'] ?? '')) !==
            strtoupper((string) $payment->currency)
        ) {
            throw new RuntimeException(
                'Korapay payment currency does not match.'
            );
        }
    }
}
