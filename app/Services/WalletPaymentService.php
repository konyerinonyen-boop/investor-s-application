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
     * Initialize a Korapay wallet deposit.
     *
     * The wallet is not credited until the payment
     * has been verified successfully.
     */
    public function initializeDeposit(
        User $user,
        string $amount,
        string $method
    ): array {
        if ($method !== 'korapay') {
            throw new InvalidArgumentException(
                'Wallet deposits must use Korapay.'
            );
        }

        $payment = DB::transaction(function () use (
            $user,
            $amount,
            $method
        ) {
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

        try {
            $gateway = $this->paymentService
                ->initializeProviderPayment($payment, $method);

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
