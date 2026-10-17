<?php

namespace App\Services;

use App\Actions\GenerateRepaymentSchedule;
use App\Factories\PaymentGatwayFactory;
use App\Models\EquityInvestment;
use App\Models\Loan;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        protected PaymentGatwayFactory $factory
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
            'full_name' => $payment->user->full_name,
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
): \Illuminate\Http\JsonResponse {
    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        'method' => ['required', 'in:paystack'],
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
    
   public function webhook(Request $request, ?string $provider = null): void
{
    if ($provider !== null && strtolower($provider) !== 'paystack') {
        throw new RuntimeException('Unsupported payment webhook provider.');
    }

    /*
     * 1. Verify Paystack signature against the raw request body.
     */
    $secret = config('services.paystack.secret_key');
    $signature = $request->header('x-paystack-signature');

    if (! $secret || ! $signature) {
        throw new RuntimeException(
            'Paystack webhook signature or secret is missing.'
        );
    }

    $computedSignature = hash_hmac(
        'sha512',
        $request->getContent(),
        $secret
    );

    if (! hash_equals($computedSignature, $signature)) {
        throw new RuntimeException('Invalid Paystack signature.');
    }

    /*
     * 2. Decode the webhook payload.
     */
    $payload = $request->json()->all();
    $event = $payload['event'] ?? null;
    $data = $payload['data'] ?? [];

    if ($event !== 'charge.success') {
        return;
    }

    $reference = $data['reference'] ?? null;

    if (! $reference) {
        throw new RuntimeException(
            'Paystack transaction reference was not provided.'
        );
    }

    /*
     * 3. Process the payment atomically.
     */
    DB::transaction(function () use ($reference, $data) {
        $payment = Payment::query()
            ->where('provider_reference', $reference)
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            throw new RuntimeException(
                'Payment not found for Paystack reference.'
            );
        }

        if ($payment->provider !== 'paystack') {
            throw new RuntimeException(
                'Payment provider does not match Paystack.'
            );
        }

        if (($data['status'] ?? null) !== 'success') {
            return;
        }

        /*
         * 4. Validate currency.
         */
        $receivedCurrency = strtoupper(
            (string) ($data['currency'] ?? '')
        );

        $expectedCurrency = strtoupper(
            (string) $payment->currency
        );

        if ($receivedCurrency !== $expectedCurrency) {
            throw new RuntimeException(
                'Payment currency does not match.'
            );
        }

        /*
         * 5. Validate amount.
         * Paystack reports NGN amounts in kobo.
         */
        $expectedMinorAmount = BigDecimal::of(
            (string) $payment->amount
        )->multipliedBy('100');

        $receivedAmount = BigDecimal::of(
            (string) ($data['amount'] ?? 0)
        );

        if (! $receivedAmount->isEqualTo($expectedMinorAmount)) {
            throw new RuntimeException(
                'Payment amount does not match.'
            );
        }

        /*
         * 6. Prevent duplicate processing.
         */
        if ($payment->status === 'successful') {
            return;
        }

        /*
         * 7. Handle wallet deposits.
         */
        if ($payment->purpose === 'wallet_deposit') {
            if (
                $payment->investment_id !== null ||
                $payment->loan_id !== null ||
                $payment->repayment_schedule_id !== null
            ) {
                throw new RuntimeException(
                    'Wallet deposit payment has conflicting linked records.'
                );
            }

            app(WalletService::class)->credit(
                $payment->user,
                (string) $payment->amount,
                'deposit',
                'wallet-deposit-' . $payment->id,
                'Wallet deposit via Paystack',
                $payment,
                [
                    'provider' => 'paystack',
                    'provider_reference' => $payment->provider_reference,
                ]
            );

            $payment->update([
                'status' => 'successful',
                'payload' => $data,
            ]);

            return;
        }

        /*
         * 8. Ensure this payment belongs to exactly one supported
         * investment or loan funding flow.
         */
        if (
            $payment->loan_id !== null &&
            $payment->investment_id !== null
        ) {
            throw new RuntimeException(
                'Payment cannot be linked to both a loan and an investment.'
            );
        }

        if (
            $payment->loan_id === null &&
            $payment->investment_id === null
        ) {
            throw new RuntimeException(
                'Payment is not linked to a supported payment purpose.'
            );
        }

        /*
         * 9. Handle incoming loan funding.
         */
        if ($payment->loan_id !== null) {
            $loan = Loan::query()
                ->whereKey($payment->loan_id)
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw new RuntimeException(
                    'Loan associated with this payment was not found.'
                );
            }

            if ((int) $loan->investor_id !== (int) $payment->user_id) {
                throw new RuntimeException(
                    'The payment user does not match the loan investor.'
                );
            }

            /*
             * If the loan is already funded, only accept the same
             * funding reference as the original payment.
             */
            if ($loan->funded_at !== null) {
                if (
                    $loan->payment_reference !==
                    $payment->provider_reference
                ) {
                    throw new RuntimeException(
                        'Loan was already funded by another payment. Reconciliation is required.'
                    );
                }

                $payment->update([
                    'status' => 'successful',
                    'payload' => $data,
                ]);

                app(GenerateRepaymentSchedule::class)
                    ->handle($loan->fresh());

                return;
            }

            /*
             * A loan must be approved before funding.
             * Throwing rolls back this database transaction.
             */
            if ($loan->status !== 'approved') {
                throw new RuntimeException(
                    'Payment succeeded but the loan is not approved. Reconciliation is required.'
                );
            }

            $payment->update([
                'status' => 'successful',
                'payload' => $data,
            ]);

            $loan->update([
                'status' => 'funded',
                'funded_at' => now(),
                'payment_reference' => $payment->provider_reference,
            ]);

            app(GenerateRepaymentSchedule::class)
                ->handle($loan->fresh());

            return;
        }

        /*
         * 10. Handle equity investment payments.
         */
        $investment = EquityInvestment::query()
            ->whereKey($payment->investment_id)
            ->lockForUpdate()
            ->first();

        if (! $investment) {
            throw new RuntimeException(
                'Investment associated with this payment was not found.'
            );
        }

        /*
         * If the investment is already completed, repair the payment
         * status without allocating units a second time.
         */
        if ($investment->status === 'completed') {
            $payment->update([
                'status' => 'successful',
                'payload' => $data,
            ]);

            return;
        }

        /*
         * Only pending investments can receive an allocation.
         */
        if ($investment->status !== 'pending_payment') {
            throw new RuntimeException(
                'Investment cannot be allocated in its current state. Reconciliation is required.'
            );
        }

        /*
         * 11. Lock the equity round and check available units.
         */
        $round = $investment->round()
            ->lockForUpdate()
            ->first();

        if (! $round) {
            throw new RuntimeException(
                'Equity round was not found.'
            );
        }

        $availableUnits = BigDecimal::of(
            (string) $round->available_units
        );

        $investmentUnits = BigDecimal::of(
            (string) $investment->units
        );

        /*
         * If the provider has received payment but the units are no
         * longer available, record the refund-required state.
         */
        if ($investmentUnits->isGreaterThan($availableUnits)) {
            $payment->update([
                'status' => 'successful',
                'payload' => $data,
            ]);

            $investment->update([
                'status' => 'refund_required',
            ]);

            return;
        }

        /*
         * 12. Allocate the units and complete the investment.
         */
        $remainingUnits = $availableUnits
            ->minus($investmentUnits)
            ->toScale(6);

        $round->forceFill([
            'available_units' => $remainingUnits->__toString(),
        ])->save();

        $payment->update([
            'status' => 'successful',
            'payload' => $data,
        ]);

        $investment->update([
            'status' => 'completed',
            'payment_reference' => $payment->provider_reference,
        ]);
    });
}
}