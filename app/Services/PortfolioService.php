<?php

namespace App\Services;

use App\Models\User;

class PortfolioService
{
    public function summary(User $user): array
    {
        return [
            'investments' => $user->investments()->with('round.product')->get(),
            'loans' => $user->loans()->with('offer.product')->get(),
            'notifications' => $user->notifications()->latest()->get(),
        ];
    }
}
