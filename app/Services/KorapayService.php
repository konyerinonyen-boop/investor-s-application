<?php

namespace App\Services;

use App\Interfaces\PaymentGatwayInterface;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KorapayService implements PaymentGatwayInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
         
    }

    
     public function initialize(array $data)
     {
        $reference = koraReference();
         $payload = [
            'amount'   => $data['amount'],
            'currency' => 'NGN', // adjust if you support multiple currencies
            'reference' => $reference,
            'customer' => [
                'name'  => $data['name'],
                'email' => $data['email'],
            ],
          ];
        Log::info('User_name', [$data['name']]);
          
        try {
            $response = Http::timeout(30)
                ->withToken(config('services.korapay.secret_key'))
                ->post("https://api.korapay.com/merchant/api/v1/charges/initialize", $payload)
                ->throw();

            $result = $response->json();
            return $result;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    
     public function webhook(Request $request)
     {
        
        Log::info('Korapay webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        $signature = $request->header('x-korapay-signature');
        $payload = $request->all();

         // Korapay signs ONLY the `data` object, not the full payload
        $dataObject = $payload['data'] ?? [];

        $computedSignature = hash_hmac(
            'sha256',
            json_encode($dataObject),
            config('services.korapay.secret_key')
        );

         Log::info('Signature verification', [
            'received' => $signature,
            'computed' => $computedSignature,
            'match' => hash_equals($computedSignature, $signature),
        ]);

         if (! $signature || ! hash_equals($computedSignature, $signature)) {
            Log::warning('Invalid webhook signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

         Log::info('Signature verified successfully');

        $event = $payload['event'] ?? null;

        Log::info('Webhook event', [
            'event' => $event,
        ]);

        // Korapay events differ from Paystack's naming
        if (! in_array($event, ['charge.success', 'charge.failed'])) {
            Log::info('Event ignored', [
                'event' => $event,
            ]);

            return response()->json(['message' => 'Event ignored'], 200);
        }
         $reference = $dataObject['reference'] ?? null;
        $amount = $dataObject['amount'] ?? 0;

        Log::info('Looking for payment', [
            'reference' => $reference,
        ]);

        $payment = Payment::where('reference', $reference)->first();

         Log::info('Payment lookup result', [
            'found' => $payment ? true : false,
            'payment' => $payment,
        ]);

        if (! $payment) {
            Log::warning('Transaction not found', [
                'reference' => $reference,
            ]);

            return response()->json(['message' => 'Transaction not found'], 404);
        }

         if (in_array($payment->status, ['success', 'failed'])) {
            Log::info('Payment already processed', [
                'status' => $payment->status,
            ]);

            return response()->json(['message' => 'Already processed'], 200);
        }
        
         DB::transaction(function () use ($payment, $event, $amount) {

            Log::info('Inside database transaction');

            if ($event === 'charge.success') {

                Log::info('Updating payment to success');

                $payment->update([
                    'status' => 'successful',
                    'amount' => $amount,
                ]);

                // TODO: credit wallet, send notification

                $wallet = Wallet::where('user_id', $payment->user_id)->lockForUpdate()->firstOrFail();

                $availableBefore = $wallet->available_balance;
                $heldBefore = $wallet->held_balance;
                $wallet->available_balance += $amount;
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'reference' => generateReference(),
                    'type' => 'credit',
                    'amount' => $amount,
                    'available_before' => $availableBefore,
                    'available_after' => $wallet->available_balance,
                    'held_before' => $heldBefore,
                    'held_after' => $wallet->held_balance,
                    'description' => "Funded my wallet"
                ]);
            } elseif ($event === 'charge.failed') {

                Log::info('Updating payment to failed');

                $payment->update([
                    'status' => 'failed',
                ]);
            }

            $payment->refresh();

            Log::info('Payment after update', [
                'status' => $payment->status,
                'amount' => $payment->amount,
            ]);
        });

        Log::info('Webhook completed successfully');

        return response()->json([
            'message' => 'Webhook processed successfully'
        ], 200);
        
     }
}
