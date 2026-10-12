<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanPaymentService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected PaymentService $paymentService)
    {
        //
    }
    /**
 * Initialize payment for one scheduled loan repayment.
 */
/**
 * Initialize payment for an existing loan funding.
 *
 * The investor pays the application to fund the loan.
 * The application will repay the investor later.
 */
        public function initializeLoanFundingPayment(
            Loan $loan,
            string $method
        ): array {
            if (
                ! auth()->check() ||
                (int) $loan->investor_id !== (int) auth()->id()
            ) {
                throw new InvalidArgumentException(
                    'You cannot fund this loan.'
                );
            }

            if (! in_array($method, ['paystack', 'manual'], true)) {
                throw new InvalidArgumentException(
                    'The selected payment method is not supported.'
                );
            }

            if ($loan->funded_at !== null) {
                throw new InvalidArgumentException(
                    'This loan has already been funded.'
                );
            }

            // Reuse an existing pending payment rather than creating duplicates.
            $existingPayment = Payment::query()
                ->where('loan_id', $loan->id)
                ->where('provider', $method)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if ($existingPayment) {
                throw new InvalidArgumentException(
                    'A pending payment already exists for this loan. Verify it before retrying.'
                );
            }

            $payment = DB::transaction(function () use ($loan, $method) {
                $lockedLoan = Loan::query()
                    ->whereKey($loan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedLoan->investor_id !== (int) auth()->id()) {
                    throw new InvalidArgumentException(
                        'You cannot fund this loan.'
                    );
                }

                if ($lockedLoan->funded_at !== null) {
                    throw new InvalidArgumentException(
                        'This loan has already been funded.'
                    );
                }

                $pendingPayment = Payment::query()
                    ->where('loan_id', $lockedLoan->id)
                    ->where('provider', $method)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($pendingPayment) {
                    throw new InvalidArgumentException(
                        'A pending payment already exists for this loan.'
                    );
                }

                return Payment::create([
                    'user_id' => $lockedLoan->investor_id,
                    'investment_id' => null,
                    'loan_id' => $lockedLoan->id,
                    'amount' => $lockedLoan->principal_amount,
                    'currency' => 'NGN',
                    'provider' => $method,
                    'provider_reference' => null,
                    'status' => 'pending',
                    'payload' => null,
                ]);
            });

            try {
                $result = $this->paymentService->initializeProviderPayment(
                    $payment,
                    $method
                );

                return [
                    'payment' => $payment->fresh(),
                    'gateway' => $result,
                ];
            } catch (\Throwable $exception) {
                $payment->update([
                    'status' => 'failed',
                ]);

                throw $exception;
            }
        }

}
