<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use Brick\Math\BigDecimal;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WalletWithdrawalService
{
    private const MINIMUM_WITHDRAWAL = '1000.00';

    private const MAXIMUM_WITHDRAWAL = '10000000.00';

    public function __construct(
        protected WalletService $walletService,
        protected KorapayService $korapay,
    ) {
    }

    public function request(User $user, string $amount): WalletWithdrawal
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new RuntimeException(
                'Enter a valid withdrawal amount with no more than two decimal places.'
            );
        }

        $withdrawalAmount = BigDecimal::of($amount)->toScale(2);

        if ($withdrawalAmount->isLessThan(self::MINIMUM_WITHDRAWAL)) {
            throw new RuntimeException(
                'The minimum withdrawal amount is ₦1,000.'
            );
        }

        if ($withdrawalAmount->isGreaterThan(self::MAXIMUM_WITHDRAWAL)) {
            throw new RuntimeException(
                'The maximum withdrawal amount is ₦10,000,000.'
            );
        }

        $configuredFee = (string) config('services.korapay.withdrawal_fee');

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $configuredFee)) {
            throw new RuntimeException(
                'The Korapay withdrawal fee is not configured correctly.'
            );
        }

        $fee = BigDecimal::of($configuredFee)->toScale(2);

        if ($fee->isLessThan('0.00')) {
            throw new RuntimeException(
                'The Korapay withdrawal fee cannot be negative.'
            );
        }

        $totalDebit = $withdrawalAmount->plus($fee)->toScale(2);

        $profile = KycProfile::query()
            ->where('user_id', $user->id)
            ->whereNotNull('bank_verified_at')
            ->whereNotNull('bank_code')
            ->whereNotNull('account_number')
            ->whereNotNull('account_name')
            ->first();

        if (! $profile) {
            throw new RuntimeException(
                'You must verify and save your bank account before withdrawing.'
            );
        }

        // Create the withdrawal and debit the wallet atomically.
        $withdrawal = DB::transaction(function () use (
            $user,
            $profile,
            $withdrawalAmount,
            $fee,
            $totalDebit
        ) {
            $reference = 'KORA-WD-' . Str::upper(Str::random(20));

            $withdrawal = WalletWithdrawal::create([
                'user_id' => $user->id,
                'amount' => (string) $withdrawalAmount,
                'fee' => (string) $fee,
                'total_debit' => (string) $totalDebit,
                'currency' => 'NGN',
                'bank_name' => $profile->bank_name,
                'bank_code' => $profile->bank_code,
                'account_number' => $profile->account_number,
                'account_name' => $profile->account_name,
                'status' => 'processing',
                'transfer_reference' => $reference,
            ]);

            $transaction = $this->walletService->debit(
                $user,
                (string) $totalDebit,
                'withdrawal',
                'wallet-withdrawal-' . $withdrawal->id,
                'Wallet withdrawal including Korapay fee',
                $withdrawal,
                [
                    'provider' => 'korapay',
                    'transfer_reference' => $reference,
                    'withdrawal_amount' => (string) $withdrawalAmount,
                    'withdrawal_fee' => (string) $fee,
                    'total_debit' => (string) $totalDebit,
                ]
            );

            $withdrawal->update([
                'wallet_transaction_id' => $transaction->id,
            ]);

            return $withdrawal->fresh();
        });

        try {
            $result = $this->korapay->disburse([
                'amount' => $withdrawal->amount,
                'bank_code' => $withdrawal->bank_code,
                'account_number' => $withdrawal->account_number,
                'email' => $user->email,
                'full_name' => $user->full_name,
                'reference' => $withdrawal->transfer_reference,
            ]);

            $withdrawal->update([
                'provider_response' => $result,
            ]);

        } catch (RequestException $exception) {
            report($exception);

            if ($this->isDefiniteAmountRejection($exception)) {
                try {
                    $this->refundRejectedWithdrawal($withdrawal);

                } catch (Throwable $refundException) {
                    report($refundException);

                    $withdrawal->update([
                        'failure_reason' =>
                            'Korapay rejected the transfer, but the automatic refund failed. Manual reconciliation is required.',
                    ]);
                }

                return $withdrawal->fresh();
            }

            // Other HTTP errors may have an uncertain outcome.
            $withdrawal->update([
                'failure_reason' =>
                    'Korapay returned an HTTP error. Transfer reconciliation is required before retrying or refunding.',
            ]);

        } catch (Throwable $exception) {
            report($exception);

            // Network errors and unexpected failures are not proof
            // that Korapay did not process the transfer.
            $withdrawal->update([
                'failure_reason' =>
                    'Payout submission outcome is unconfirmed. Reconciliation is required before retrying or refunding.',
            ]);
        }

        return $withdrawal->fresh();
    }

    /**
     * Recognise Korapay's explicit minimum/maximum transfer rejection.
     */
    private function isDefiniteAmountRejection(
        RequestException $exception
    ): bool {
        $response = $exception->response;

        if ($response->status() !== 409) {
            return false;
        }

        $message = strtolower(
            (string) (
                $response->json('message')
                ?? $response->body()
            )
        );

        return str_contains(
            $message,
            'between ngn 1000 and ngn 10000000'
        );
    }

    /**
     * Refund a definitively rejected withdrawal exactly once.
     */
    private function refundRejectedWithdrawal(
        WalletWithdrawal $withdrawal
    ): void {
        DB::transaction(function () use ($withdrawal) {
            $w = WalletWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($w->status === 'failed') {
                return;
            }

            if ($w->status !== 'processing') {
                throw new RuntimeException(
                    'Withdrawal is not processing. Refund was not attempted.'
                );
            }

            $debit = WalletTransaction::query()
                ->whereKey($w->wallet_transaction_id)
                ->firstOrFail();

            if (
                $debit->direction !== 'debit' ||
                $debit->type !== 'withdrawal' ||
                $debit->reference !== 'wallet-withdrawal-' . $w->id ||
                (int) $debit->source_id !== (int) $w->id ||
                ! BigDecimal::of((string) $debit->amount)
                    ->isEqualTo((string) $w->total_debit)
            ) {
                throw new RuntimeException(
                    'Original withdrawal debit does not match. Refund was not attempted.'
                );
            }

            $refundReference = 'wallet-withdrawal-refund-' . $w->id;

            $existingRefund = WalletTransaction::query()
                ->where('reference', $refundReference)
                ->first();

            if ($existingRefund) {
                if (
                    $existingRefund->direction !== 'credit' ||
                    $existingRefund->type !== 'withdrawal_refund' ||
                    ! BigDecimal::of((string) $existingRefund->amount)
                        ->isEqualTo((string) $w->total_debit)
                ) {
                    throw new RuntimeException(
                        'An existing refund does not match this withdrawal.'
                    );
                }
            } else {
                $this->walletService->credit(
                    $w->user,
                    (string) $w->total_debit,
                    'withdrawal_refund',
                    $refundReference,
                    'Refund for rejected Korapay withdrawal #' . $w->id,
                    $w,
                    [
                        'provider' => 'korapay',
                        'transfer_reference' => $w->transfer_reference,
                        'original_debit' => (string) $w->total_debit,
                        'reason' => 'Korapay rejected the transfer amount.',
                    ]
                );
            }

            $w->update([
                'status' => 'failed',
                'failure_reason' =>
                    'Korapay rejected the transfer amount. The full wallet debit, including the fee, was refunded.',
            ]);
        }, 3);
    }
}
