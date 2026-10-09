<?php

namespace App\Services;

use App\Factories\PaymentGatwayFactory;
use App\Models\EquityInvestment;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        protected PaymentGatwayFactory $factory
    ) {
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
            $verification = $this->verifyPayment($existingPayment);

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
            $result = $this->initializeProviderPayment(
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
    
    public function webhook(Request $request): void
    {
        $webhookId = (string) \Illuminate\Support\Str::uuid();

        Log::info('PAYSTACK WEBHOOK: Received request', [
            'webhook_id' => $webhookId,
            'method' => $request->method(),
            'content_type' => $request->header('content-type'),
            'has_signature' => $request->hasHeader('x-paystack-signature'),
            'body_length' => strlen($request->getContent()),
        ]);

        /*
        * 1. Verify the Paystack signature against the RAW body.
        */
        $secret = config('services.paystack.secret_key');
        $signature = $request->header('x-paystack-signature');

        if (! $secret || ! $signature) {
            Log::warning('PAYSTACK WEBHOOK: Missing secret or signature', [
                'webhook_id' => $webhookId,
                'secret_configured' => (bool) $secret,
                'signature_present' => (bool) $signature,
            ]);

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
            Log::warning('PAYSTACK WEBHOOK: Invalid signature', [
                'webhook_id' => $webhookId,
            ]);

            throw new RuntimeException('Invalid Paystack signature.');
        }

        Log::info('PAYSTACK WEBHOOK: Signature verified', [
            'webhook_id' => $webhookId,
        ]);

        /*
        * 2. Decode the JSON BEFORE reading the event.
        */
        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('PAYSTACK WEBHOOK: Payload decoded', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'reference' => $data['reference'] ?? null,
            'provider_status' => $data['status'] ?? null,
            'currency' => $data['currency'] ?? null,
            'amount_minor' => $data['amount'] ?? null,
        ]);

        /*
        * 3. Ignore events this handler does not process.
        */
        if ($event !== 'charge.success') {
            Log::info('PAYSTACK WEBHOOK: Event ignored', [
                'webhook_id' => $webhookId,
                'event' => $event,
            ]);

            return;
        }

        $reference = $data['reference'] ?? null;

        if (! $reference) {
            Log::error('PAYSTACK WEBHOOK: Missing transaction reference', [
                'webhook_id' => $webhookId,
            ]);

            throw new RuntimeException(
                'Paystack transaction reference was not provided.'
            );
        }

        /*
        * 4. Process the payment inside a database transaction.
        */
        DB::transaction(function () use (
            $webhookId,
            $reference,
            $data
        ) {
            Log::info('PAYSTACK WEBHOOK: Looking up payment', [
                'webhook_id' => $webhookId,
                'reference' => $reference,
            ]);

            $payment = Payment::query()
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::error('PAYSTACK WEBHOOK: Payment record not found', [
                    'webhook_id' => $webhookId,
                    'reference' => $reference,
                ]);

                throw new RuntimeException(
                    'Payment not found for Paystack reference.'
                );
            }

            Log::info('PAYSTACK WEBHOOK: Payment record found', [
                'webhook_id' => $webhookId,
                'payment_id' => $payment->id,
                'investment_id' => $payment->investment_id,
                'provider' => $payment->provider,
                'local_status' => $payment->status,
                'currency' => $payment->currency,
                'amount' => (string) $payment->amount,
            ]);

            /*
            * 5. Confirm provider and transaction status.
            */
            if ($payment->provider !== 'paystack') {
                Log::error('PAYSTACK WEBHOOK: Provider mismatch', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'provider' => $payment->provider,
                ]);

                throw new RuntimeException(
                    'Payment provider does not match Paystack.'
                );
            }

            if (($data['status'] ?? null) !== 'success') {
                Log::warning('PAYSTACK WEBHOOK: Transaction not successful', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'provider_status' => $data['status'] ?? null,
                ]);

                return;
            }

            /*
            * 6. Validate currency.
            */
            $receivedCurrency = strtoupper(
                (string) ($data['currency'] ?? '')
            );

            $expectedCurrency = strtoupper(
                (string) $payment->currency
            );

            if ($receivedCurrency !== $expectedCurrency) {
                Log::critical('PAYSTACK WEBHOOK: Currency mismatch', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'expected_currency' => $expectedCurrency,
                    'received_currency' => $receivedCurrency,
                ]);

                throw new RuntimeException(
                    'Payment currency does not match.'
                );
            }

            /*
            * 7. Validate amount.
            * Paystack sends the amount in kobo for NGN.
            */
            $expectedMinorAmount = BigDecimal::of(
                (string) $payment->amount
            )->multipliedBy('100');

            $receivedAmount = BigDecimal::of(
                (string) ($data['amount'] ?? 0)
            );

            if (! $receivedAmount->isEqualTo($expectedMinorAmount)) {
                Log::critical('PAYSTACK WEBHOOK: Amount mismatch', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'expected_minor_amount' => $expectedMinorAmount->__toString(),
                    'received_minor_amount' => $receivedAmount->__toString(),
                ]);

                throw new RuntimeException(
                    'Payment amount does not match.'
                );
            }

            /*
            * 8. Prevent duplicate processing.
            */
            if ($payment->status === 'successful') {
                Log::info('PAYSTACK WEBHOOK: Already processed', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'reference' => $reference,
                ]);

                return;
            }

            /*
            * 9. Load and lock the investment.
            */
            $investment = EquityInvestment::query()
                ->whereKey($payment->investment_id)
                ->lockForUpdate()
                ->first();

            if (! $investment) {
                Log::critical('PAYSTACK WEBHOOK: Investment not found', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'investment_id' => $payment->investment_id,
                ]);

                throw new RuntimeException(
                    'Investment associated with this payment was not found.'
                );
            }

            Log::info('PAYSTACK WEBHOOK: Investment found', [
                'webhook_id' => $webhookId,
                'investment_id' => $investment->id,
                'investment_status' => $investment->status,
                'units' => (string) $investment->units,
            ]);

            /*
            * 10. If already completed, repair payment status if needed.
            */
            if ($investment->status === 'completed') {
                $payment->update([
                    'status' => 'successful',
                    'payload' => $data,
                ]);

                Log::info('PAYSTACK WEBHOOK: Investment already completed', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'investment_id' => $investment->id,
                ]);

                return;
            }

            /*
            * 11. Only pending investments can be allocated.
            */
            if ($investment->status !== 'pending_payment') {
                Log::critical('PAYSTACK WEBHOOK: Investment cannot be allocated', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'investment_id' => $investment->id,
                    'investment_status' => $investment->status,
                ]);

                /*
                * Do not silently discard a confirmed successful payment.
                * Keep the payment record and flag for reconciliation.
                */
                $payment->update([
                    'status' => 'successful',
                    'payload' => $data,
                ]);

                Log::critical('PAYSTACK WEBHOOK: Manual reconciliation required', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'investment_id' => $investment->id,
                ]);

                return;
            }

            /*
            * 12. Lock the equity round and check available units.
            */
            $round = $investment->round()
                ->lockForUpdate()
                ->first();

            if (! $round) {
                Log::critical('PAYSTACK WEBHOOK: Equity round not found', [
                    'webhook_id' => $webhookId,
                    'investment_id' => $investment->id,
                ]);

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

            Log::info('PAYSTACK WEBHOOK: Checking equity availability', [
                'webhook_id' => $webhookId,
                'round_id' => $round->id,
                'available_units' => $availableUnits->__toString(),
                'requested_units' => $investmentUnits->__toString(),
            ]);

            /*
            * 13. Mark the payment successful even if allocation
            * cannot be completed. Never throw here: that would
            * roll back the payment update.
            */
            $payment->update([
                'status' => 'successful',
                'payload' => $data,
            ]);

            if ($investmentUnits->isGreaterThan($availableUnits)) {
                $investment->update([
                    'status' => 'refund_required',
                ]);

                Log::critical('PAYSTACK WEBHOOK: Payment succeeded but units unavailable', [
                    'webhook_id' => $webhookId,
                    'payment_id' => $payment->id,
                    'investment_id' => $investment->id,
                    'round_id' => $round->id,
                    'reference' => $reference,
                ]);

                return;
            }

            /*
            * 14. Deduct units and complete investment.
            */
            $remainingUnits = $availableUnits
                ->minus($investmentUnits)
                ->toScale(6);

            $round->forceFill([
                'available_units' => $remainingUnits->__toString(),
            ])->save();

            $investment->update([
                'status' => 'completed',
                'payment_reference' => $payment->provider_reference,
            ]);

            Log::info('PAYSTACK WEBHOOK: Payment and investment completed', [
                'webhook_id' => $webhookId,
                'payment_id' => $payment->id,
                'investment_id' => $investment->id,
                'round_id' => $round->id,
                'reference' => $reference,
                'remaining_units' => $remainingUnits->__toString(),
            ]);
        });

        Log::info('PAYSTACK WEBHOOK: Processing finished', [
            'webhook_id' => $webhookId,
            'reference' => $reference,
        ]);
    }
}