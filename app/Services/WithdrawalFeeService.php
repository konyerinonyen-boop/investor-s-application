<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use RuntimeException;

class WithdrawalFeeService
{
    public function calculate(string $amount): string
    {
        $configuredFee = config('services.korapay.withdrawal_fee');

        if (
            ! is_numeric($configuredFee) ||
            (float) $configuredFee < 0
        ) {
            throw new RuntimeException(
                'Withdrawal fees are not configured. Please contact support.'
            );
        }

        $fee = BigDecimal::of((string) $configuredFee);

        if ($fee->getScale() > 2) {
            throw new RuntimeException(
                'The configured withdrawal fee is invalid.'
            );
        }

        return (string) $fee->toScale(2);
    }

    public function totalDebit(string $amount, string $fee): string
    {
        return (string) BigDecimal::of($amount)
            ->plus(BigDecimal::of($fee))
            ->toScale(2);
    }
}