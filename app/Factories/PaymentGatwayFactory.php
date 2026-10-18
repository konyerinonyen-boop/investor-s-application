<?php

namespace App\Factories;

use App\Interfaces\PaymantGateWayInterface;
use App\Services\KorapayService;
use App\Services\ManualPaymentService;
use App\Services\PaystackService;
use InvalidArgumentException;

class PaymentGatwayFactory
{
    public function make(string $gateway): PaymantGateWayInterface
    {
        return match ($gateway) {

            'korapay' => app(KorapayService::class),

            'paystack' => app(PaystackService::class),

            'manual' => app(ManualPaymentService::class),

            default => throw new InvalidArgumentException(
                "Unsupported payment gateway: {$gateway}"
            ),
        };
    }
}

