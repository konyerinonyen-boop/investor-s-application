<?php

namespace App\Services;

use App\Models\EquityInvestment;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvestmentPaymentService
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    public function initializeInvestmentPayment(
        EquityInvestment $investment,
    ): array {
        if (
            ! auth()->check() ||
            (int) $investment->investor_id !== (int) auth()->id()
        ) {
            throw new InvalidArgumentException(
                'You cannot make payment for this investment.'
            );
        }

        return DB::transaction(function () use ($investment) {
            $lockedInvestment = EquityInvestment::query()
                ->whereKey($investment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $lockedInvestment->investor_id !==
                (int) auth()->id()
            ) {
                throw new InvalidArgumentException(
                    'You cannot make payment for this investment.'
                );
            }

            if ($lockedInvestment->status !== 'pending_payment') {
                throw new InvalidArgumentException(
                    'This investment is no longer awaiting payment.'
                );
            }

            $round = $lockedInvestment->round()
                ->lockForUpdate()
                ->first();

            if (! $round) {
                throw new InvalidArgumentException(
                    'The equity round could not be found.'
                );
            }

            $availableUnits = BigDecimal::of(
                (string) $round->available_units
            );

            $requestedUnits = BigDecimal::of(
                (string) $lockedInvestment->units
            );

            if ($requestedUnits->isGreaterThan($availableUnits)) {
                throw new InvalidArgumentException(
                    'There are not enough equity units available.'
                );
            }

            $reference = 'wallet-equity-investment-'
                . $lockedInvestment->id;

            // Create the payment inside the transaction.
            $payment = Payment::create([
                'user_id' => $lockedInvestment->investor_id,
                'purpose' => 'equity_investment',
                'investment_id' => $lockedInvestment->id,
                'loan_id' => null,
                'repayment_schedule_id' => null,
                'amount' => $lockedInvestment->amount,
                'currency' => 'NGN',
                'provider' => 'wallet',
                'provider_reference' => $reference,
                'status' => 'pending',
                'payload' => null,
            ]);

            // This checks the wallet balance and deducts the money.
            // If the balance is insufficient, the transaction rolls back.
            $this->walletService->debit(
                $lockedInvestment->investor,
                (string) $lockedInvestment->amount,
                'equity_investment',
                'equity-investment-' . $lockedInvestment->id,
                'Wallet payment for equity investment #'
                    . $lockedInvestment->id,
                $payment,
                [
                    'investment_id' => $lockedInvestment->id,
                    'payment_id' => $payment->id,
                ]
            );

            // Deduct the units from the equity round.
            $remainingUnits = $availableUnits
                ->minus($requestedUnits)
                ->toScale(6);

            $round->forceFill([
                'available_units' => $remainingUnits->__toString(),
            ])->save();

            // Complete the investment and mark payment successful.
            $lockedInvestment->update([
                'status' => 'completed',
                'payment_reference' => $reference,
            ]);

            $payment->update([
                'status' => 'successful',
            ]);

            return [
                'payment' => $payment->fresh(),
                'gateway' => null,
            ];
        }, 3);
    }
}
