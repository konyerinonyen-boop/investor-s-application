<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\WalletWithdrawal;
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
        if (! is_numeric($amount) || (float) $amount < 100) {
            throw new RuntimeException(
                'The minimum withdrawal amount is ₦100.'
            );
        }

        if (round((float) $amount, 2) !== (float) $amount) {
            throw new RuntimeException(
                'The withdrawal amount cannot have more than two decimal places.'
            );
        }

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

        // Save and debit atomically before calling the external provider.
        $withdrawal = DB::transaction(function () use (
            $user,
            $profile,
            $amount
        ) {
            $reference = 'KORA-WD-' . Str::upper(Str::random(20));

            $withdrawal = WalletWithdrawal::create([
                'user_id' => $user->id,
                'amount' => number_format((float) $amount, 2, '.', ''),
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
                number_format((float) $amount, 2, '.', ''),
                'withdrawal',
                'wallet-withdrawal-' . $withdrawal->id,
                'Wallet withdrawal to bank account',
                $withdrawal,
                [
                    'provider' => 'korapay',
                    'transfer_reference' => $reference,
                ]
            );

            $withdrawal->update([
                'wallet_transaction_id' => $transaction->id,
            ]);

            return $withdrawal->fresh();
        });

        /*
         * Do not automatically refund or retry if this request throws:
         * the provider might have received it despite a timeout.
         * Reconcile the transfer reference before taking further action.
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

            // Keep it processing until the same reference is reconciled.
            $withdrawal->update([
                'failure_reason' =>
                    'Payout submission outcome is unconfirmed. Reconciliation is required.',
            ]);
        }

        return $withdrawal->fresh();
    }
}
