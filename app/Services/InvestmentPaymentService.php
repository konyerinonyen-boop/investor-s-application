<?php

namespace App\Services;

use App\Models\EquityInvestment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvestmentPaymentService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected PaymentService $paymentService)
    {
        //
    }
    /**
     * Initialize payment for an existing investment.
     *
     * The investment must already exist.
     */

   
     public function initializeInvestmentPayment(
        EquityInvestment $investment,
        string $method
    ): array {
        if (
            ! auth()->check() ||
            (int) $investment->investor_id !== (int) auth()->id()
        ) {
            throw new InvalidArgumentException(
                'You cannot make payment for this investment.'
            );
        }

        if (! in_array($method, ['paystack', 'manual'], true)){
            throw new InvalidArgumentException(
                'The selected payment method is not supported.'
            );
        }

        /*
        * Check for an existing pending payment BEFORE opening
        * a database transaction. Never call Paystack while
        * holding database locks.
        */
        $existingPayment = Payment::query()
            ->where('investment_id', $investment->id)
            ->where('provider', $method)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPayment) {
            if ($method !== 'paystack') {
                throw new InvalidArgumentException(
                    'The previous payment must be checked before retrying.'
                );
            }

            // Check the actual transaction status with Paystack.
            $verification = $this->paymentService->verifyPayment($existingPayment);

            if ($verification instanceof \Illuminate\Http\Client\Response) {
                $verification = $verification->json();
            }

            $providerStatus =
                data_get($verification, 'data.status')
                ?? data_get($verification, 'data.data.status');

            if ($providerStatus === 'success') {
                throw new InvalidArgumentException(
                    'The previous payment succeeded. Do not retry it.'
                );
            }

            if (! in_array($providerStatus, ['abandoned', 'failed'], true)) {
                throw new InvalidArgumentException(
                    'The previous payment is not confirmed as unsuccessful. Verify it before retrying.'
                );
            }

            // Allow a retry by closing the old unsuccessful attempt.
            $existingPayment->update([
                'status' => $providerStatus,
            ]);

        }

        /*
        * Lock the investment and create a fresh payment record.
        */
        $payment = DB::transaction(function () use (
            $investment,
            $method
        ) {
            $lockedInvestment = EquityInvestment::query()
                ->whereKey($investment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvestment->status !== 'pending_payment') {
                throw new InvalidArgumentException(
                    'This investment is no longer awaiting payment.'
                );
            }

            /*
            * Recheck to reduce duplicate payment creation during
            * concurrent requests.
            */
            $pendingPayment = Payment::query()
                ->where('investment_id', $lockedInvestment->id)
                ->where('provider', $method)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($pendingPayment) {
                throw new InvalidArgumentException(
                    'A pending payment already exists. Verify it before retrying.'
                );
            }

            return Payment::create([
                'user_id' => $lockedInvestment->investor_id,
                'investment_id' => $lockedInvestment->id,
                'loan_id' => null,
                'amount' => $lockedInvestment->amount,

                // Use NGN only if the investment amount is denominated in naira.
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
