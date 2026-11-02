<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\WalletWithdrawal;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WalletWithdrawalService
{
    public function __construct(
        protected WalletService $walletService,
        protected KorapayService $korapay,
    ) {
    }

    public function request(User $user, string $amount): WalletWithdrawal
    {
        // Accept only positive naira amounts with at most 2 decimal places.
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new RuntimeException(
                'Enter a valid withdrawal amount with no more than two decimal places.'
            );
        }

        $withdrawalAmount = BigDecimal::of($amount)->toScale(2);

        if ($withdrawalAmount->isLessThan('100.00')) {
            throw new RuntimeException(
                'The minimum withdrawal amount is ₦100.'
            );
        }

        // Read the fee from config/services.php, not from user input.
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

        // Save the withdrawal and deduct amount + fee atomically.
        // WalletService checks the available balance under a database lock.
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

        /*
         * Send only the requested withdrawal amount to Korapay.
         * The fee is retained by the platform to cover the payout charge.
         *
         * If the request outcome is uncertain, do not refund or retry
         * until the transfer reference has been reconciled with Korapay.
         */
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
        } catch (Throwable $exception) {
            report($exception);

            $withdrawal->update([
                'failure_reason' =>
                    'Payout submission outcome is unconfirmed. Reconciliation is required.',
            ]);
        }

        return $withdrawal->fresh();
    }
}
