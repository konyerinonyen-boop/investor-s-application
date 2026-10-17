<?php

namespace App\Services;

use App\Actions\GenerateRepaymentSchedule;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanPaymentService
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    public function initializeLoanFundingPayment(
        Loan $loan,
    ): array {
        if (
            ! auth()->check() ||
            (int) $loan->investor_id !== (int) auth()->id()
        ) {
            throw new InvalidArgumentException(
                'You cannot fund this loan.'
            );
        }

        return DB::transaction(function () use ($loan) {
            $lockedLoan = Loan::query()
                ->whereKey($loan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $lockedLoan->investor_id !==
                (int) auth()->id()
            ) {
                throw new InvalidArgumentException(
                    'You cannot fund this loan.'
                );
            }

            if ($lockedLoan->funded_at !== null) {
                throw new InvalidArgumentException(
                    'This loan has already been funded.'
                );
            }


            $reference = 'wallet-loan-funding-' . $lockedLoan->id;

            $payment = Payment::create([
                'user_id' => $lockedLoan->investor_id,
                'purpose' => 'loan_funding',
                'investment_id' => null,
                'loan_id' => $lockedLoan->id,
                'repayment_schedule_id' => null,
                'amount' => $lockedLoan->principal_amount,
                'currency' => 'NGN',
                'provider' => 'wallet',
                'provider_reference' => $reference,
                'status' => 'pending',
                'payload' => null,
            ]);

            // Deduct the loan principal from the investor's wallet.
            // Insufficient balance causes the entire transaction to roll back.
            $this->walletService->debit(
                $lockedLoan->investor,
                (string) $lockedLoan->principal_amount,
                'loan_funding',
                'loan-funding-' . $lockedLoan->id,
                'Wallet funding for loan #' . $lockedLoan->id,
                $payment,
                [
                    'loan_id' => $lockedLoan->id,
                    'payment_id' => $payment->id,
                ]
            );

            // Mark the loan as funded only after the wallet debit succeeds.
            $lockedLoan->update([
                'status' => 'funded',
                'funded_at' => now(),
                'payment_reference' => $reference,
            ]);

            $payment->update([
                'status' => 'successful',
            ]);

            // Create the repayment schedule after funding.
            app(GenerateRepaymentSchedule::class)
                ->handle($lockedLoan->fresh());

            return [
                'payment' => $payment->fresh(),
                'gateway' => null,
            ];
        }, 3);
    }
}
