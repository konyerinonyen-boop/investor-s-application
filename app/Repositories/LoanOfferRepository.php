<?php

namespace App\Repositories;

use App\Models\LoanOffer;

class LoanOfferRepository
{
    public function findById(int $id): ?LoanOffer
    {
        return LoanOffer::with('product')->find($id);
    }
}
