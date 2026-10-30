<?php

namespace App\Services;
use App\Factories\PaymentGatwayFactory;
use App\Models\EquityInvestment;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        protected PaymentGatwayFactory $factory,
        protected KorapayWebhookService $korapayWebhookService
    ) {
    }

    /**
     * Call the actual payment provider.
     */
    public function initializeProviderPayment(   Payment $payment,
        string $method)
     : array {
        $paymentMethod = $this->factory->make($method);

        $amount = BigDecimal::of(
            (string) $payment->amount
        );

        $data = [
            'amount' => $amount->__toString(),

            /*
             * The provider needs the investor's email.
             */
            'email' => $payment->user->email,
            'name' => $payment->user->full_name,
        ];

        /*
         * Paystack expects amount in the smallest
         * currency unit.
         *
         * Example:
         *
         * $10,000.00
         *
         * becomes:
         *
         * 1,000,000
         */
        if ($method === 'paystack') {
            $data['amount'] = (int) round(
                ((float) $amount->__toString()) * 100
            );
        }

        $result = $paymentMethod->initialize($data);

        $reference = $result['data']['reference'] ?? null;

        if (! $reference) {
            throw new RuntimeException(
                'Payment provider did not return a payment reference.'
            );
        }

        /*
         * Save provider reference against the SAME payment.
         */
        $payment->update([
            'provider_reference' => $reference,

            /*
             * Store provider-specific information.
             */
            'payload' => $result['data'] ?? null,
            /**
             * Start a timer for 30 mins
             */
            'checkout_expires_at' => now()->addMinutes(30),
        ]);

        /*
         * Connect the investment to the payment reference too.
         */
        if ($payment->investment_id) {
            EquityInvestment::query()
                ->whereKey($payment->investment_id)
                ->update([
                    'payment_reference' => $reference,
                ]);
        }

        return $result;
    }

    public function depositToWallet(
    Request $request,
    WalletPaymentService $walletPaymentService
):JsonResponse {
    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
       'method' => ['required', 'in:korapay'],
    ]);

    try {
        $result = $walletPaymentService->initializeDeposit(
            $request->user(),
            (string) $validated['amount'],
            $validated['method']
        );

        return response()->json([
            'success' => true,
            'message' => 'Wallet deposit initialized successfully.',
            'data' => [
                'payment' => $result['payment'],
                'gateway' => $result['gateway'],
            ],
        ], 201);
    } catch (\Throwable $exception) {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Unable to initialize the wallet deposit.',
        ], 500);
    }
}

    public function verifyPayment(Payment $payment): array
    {
        if (
            $payment->provider !== 'paystack' ||
            ! $payment->provider_reference
        ) {
            throw new RuntimeException(
                'This payment cannot be verified with Paystack.'
            );
        }

        $paystack = $this->factory->make($payment->provider);

        return $paystack->verify(
            $payment->provider_reference
        );
    }
    
public function webhook(
    Request $request,
    string $provider,
): JsonResponse {
    try {
        $this->korapayWebhookService->handle($request, $provider);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received and processed.',
        ], 200);

    } catch (Throwable $exception) {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Webhook processing failed. Reconciliation may be required.',
        ], 500);
    }
}


public function withdrawFromWallet(
    Request $request,
    WalletWithdrawalService $withdrawalService,
): JsonResponse {
    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'min:100', 'decimal:0,2'],
    ]);

    try {
        $withdrawal = $withdrawalService->request(
            $request->user(),
            (string) $validated['amount'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request recorded.',
            'data' => [
                'withdrawal' => $withdrawal,
            ],
        ], 201);
    } catch (\RuntimeException $exception) {
        if (
            in_array($exception->getMessage(), [
                'Insufficient wallet balance.',
                'The minimum withdrawal amount is ₦100.',
                'The withdrawal amount cannot have more than two decimal places.',
                'You must verify and save your bank account before withdrawing.',
            ], true)
        ) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Unable to process the withdrawal request.',
        ], 500);
    } catch (\Throwable $exception) {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Unable to process the withdrawal request.',
        ], 500);
    }
}


}