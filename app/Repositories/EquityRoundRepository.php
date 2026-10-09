<?php

namespace App\Repositories;

use App\Models\EquityRound;
use Brick\Math\BigDecimal;

class EquityRoundRepository
{
    public function findById(int $id): ?EquityRound
    {
        return EquityRound::query()
            ->with('product')
            ->find($id);
    }

    public function findForUpdate(int $id): ?EquityRound
    {
        return EquityRound::query()
            ->with('product')
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

   public function reserveUnits(
    EquityRound $round,
    BigDecimal $units
): void {
    $available = BigDecimal::of(
        (string) $round->available_units
    );

    if ($units->isNegativeOrZero()) {
        throw new \InvalidArgumentException(
            'Units to reserve must be greater than zero.'
        );
    }

    if ($units->isGreaterThan($available)) {
        throw new \RuntimeException(
            'There are not enough units available.'
        );
    }

    $remaining = $available->minus($units);

    $round->forceFill([
        'available_units' => $remaining->toScale(6)->__toString(),
    ])->save();
}
public function releaseUnits(
    EquityRound $round,
    BigDecimal $units
): void {
    if ($units->isNegativeOrZero()) {
        throw new \InvalidArgumentException(
            'Units to release must be greater than zero.'
        );
    }

    $available = BigDecimal::of(
        (string) $round->available_units
    );

    $remaining = $available
        ->plus($units)
        ->toScale(6);

    $round->forceFill([
        'available_units' => $remaining->__toString(),
    ])->save();
}
}