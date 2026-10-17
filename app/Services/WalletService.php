<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /**
     * Add money to a user's wallet.
     */
    public function credit(
        User $user,
        string $amount,
        string $type,
        string $reference,
        ?string $description = null,
        ?Model $source = null,
        array $metadata = [],
    ): WalletTransaction {
        return $this->changeBalance(
            $user,
            $amount,
            $type,
            'credit',
            $reference,
            $description,
            $source,
            $metadata,
        );
    }

    /**
     * Deduct money from a user's wallet.
     */
    public function debit(
        User $user,
        string $amount,
        string $type,
        string $reference,
        ?string $description = null,
        ?Model $source = null,
        array $metadata = [],
    ): WalletTransaction {
        return $this->changeBalance(
            $user,
            $amount,
            $type,
            'debit',
            $reference,
            $description,
            $source,
            $metadata,
        );
    }

    /**
     * Apply a credit or debit atomically.
     */
    private function changeBalance(
        User $user,
        string $amount,
        string $type,
        string $direction,
        string $reference,
        ?string $description,
        ?Model $source,
        array $metadata,
    ): WalletTransaction {
        $value = BigDecimal::of($amount);

            if ($value->getScale() > 2) {
                throw new RuntimeException(
                    'The transaction amount cannot have more than two decimal places.'
                );
            }

            $value = $value->toScale(2);

        if ($value->isLessThanOrEqualTo('0')) {
            throw new RuntimeException('The transaction amount must be greater than zero.');
        }

        if (trim($reference) === '') {
            throw new RuntimeException('A transaction reference is required.');
        }

        if (trim($type) === '') {
            throw new RuntimeException('A transaction type is required.');
        }

        return DB::transaction(function () use (
            $user,
            $value,
            $type,
            $direction,
            $reference,
            $description,
            $source,
            $metadata,
        ) {
            // Create the wallet if this user does not have one yet.
            // The unique user_id constraint prevents multiple wallets.
            Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'NGN', 'balance' => '0.00'],
            );

            // Lock this wallet so simultaneous transactions cannot
            // spend or credit a stale balance.
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // A reference must only ever represent one ledger entry.
            $existing = WalletTransaction::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $sameTransaction =
                    (int) $existing->wallet_id === (int) $wallet->id
                    && $existing->type === $type
                    && $existing->direction === $direction
                    && BigDecimal::of((string) $existing->amount)
                        ->isEqualTo($value);

                if (! $sameTransaction) {
                    throw new RuntimeException(
                        'This transaction reference has already been used for a different transaction.'
                    );
                }

                // Safe retry: do not change the balance a second time.
                return $existing;
            }

            $currentBalance = BigDecimal::of((string) $wallet->balance);

            if (
                $direction === 'debit'
                && $currentBalance->isLessThan($value)
            ) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $newBalance = $direction === 'credit'
                ? $currentBalance->plus($value)
                : $currentBalance->minus($value);

            $wallet->balance = (string) $newBalance->toScale(2);
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'direction' => $direction,
                'amount' => (string) $value,
                'balance_after' => (string) $newBalance->toScale(2),
                'reference' => $reference,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'metadata' => $metadata,
            ]);
        }, 3);
    }
}
