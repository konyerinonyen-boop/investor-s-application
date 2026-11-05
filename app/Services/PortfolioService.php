<?php

namespace App\Services;

use App\Models\User;

class PortfolioService
{
    public function summary(User $user): array
    {
        return [
            // Original investment records remain available as history.
            'investments' => $user->investments()
                ->with('round.product')
                ->get(),

            // Current ownership after purchases and future resales.
            'equity_holdings' => $user->equityHoldings()
                ->with('round.product')
                ->where('units', '>', 0)
                ->get(),

            'loans' => $user->loans()
                ->with('offer.product')
                ->get(),

            'notifications' => $user->notifications()
                ->latest()
                ->get(),
        ];
    }
}
