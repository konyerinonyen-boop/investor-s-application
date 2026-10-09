<?php

namespace App\Services;

use App\Interfaces\PaymantGateWayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaystackService implements PaymantGateWayInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

     public function initialize(array $data)
     {
        $payload = [
        'email'        => $data['email'],
        'amount'       => $data['amount'],
        'full_name' => $data['full_name']
        //  'callback_url' => route('payment.callback'), // <-- added here
        ];
        Log::info('User_name', [$data['full_name']]);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post("https://api.paystack.co/transaction/initialize", $payload)->throw();

        $result = $response->json();

        return $result;
     }
public function verify(string $reference): array
{
    $response = Http::withToken(
        config('services.paystack.secret_key')
    )
        ->acceptJson()
        ->get(
            "https://api.paystack.co/transaction/verify/{$reference}"
        )
        ->throw();

    $result = $response->json();

    if (($result['status'] ?? false) !== true) {
        throw new RuntimeException(
            'Paystack could not verify this transaction.'
        );
    }

    return $result;
}


}