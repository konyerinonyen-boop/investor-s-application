<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletPaymentService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    /**
     * Initialize a Paystack deposit into the user's wallet.
     */
    public function initializeDeposit(
        User $user,
        string $amount,
        string $method
    ): array {
        /*
         * Wallet deposits must use Paystack because the wallet
         * is credited automatically after verified payment.
         */
        if ($method !== 'paystack') {
            throw new InvalidArgumentException(
                'Wallet deposits currently support Paystack only.'
            );
        }

        /*
         * Create a pending payment specifically for a wallet deposit.
         * This does not add money to the wallet yet.
         */
        $payment = DB::transaction(function () use ($user, $amount, $method) {
            return Payment::create([
                'user_id' => $user->id,
                'purpose' => 'wallet_deposit',
                'investment_id' => null,
                'loan_id' => null,
                'repayment_schedule_id' => null,
                'amount' => $amount,
                'currency' => 'NGN',
                'provider' => $method,
                'provider_reference' => null,
                'status' => 'pending',
                'payload' => null,
            ]);
        });

        /*
         * Call Paystack after the database transaction finishes.
         * This avoids holding database locks while contacting Paystack.
         */
        try {
            $gateway = $this->paymentService->initializeProviderPayment(
                $payment,
                $method
            );

            return [
                'payment' => $payment->fresh(),
                'gateway' => $gateway,
            ];
        } catch (\Throwable $exception) {
            $payment->update([
                'status' => 'failed',
            ]);

            throw $exception;
        }
    }
}