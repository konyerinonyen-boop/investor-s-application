<?php

namespace App\Repositories;

use App\Models\EquityRound;
use InvalidArgumentException;

class EquityRoundRepository
{
    public function findById(int $id): ?EquityRound
    {
        return EquityRound::with('product')->find($id);
    }

    public function reserveUnits(EquityRound $round, float $units): EquityRound
    {
        if ($units <= 0) {
            throw new InvalidArgumentException('Investment quantity must be greater than zero.');
        }

        if ($round->available_units < $units) {
            throw new InvalidArgumentException('Not enough available units remaining in this round.');
        }

        $round->available_units = (float) $round->available_units - $units;
        $round->save();

        return $round->fresh();
    }
}
