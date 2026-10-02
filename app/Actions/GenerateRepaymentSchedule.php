<?php

namespace App\Actions;

use App\Models\Loan;
use Illuminate\Support\Collection;

class GenerateRepaymentSchedule
{
    public function handle(Loan $loan, int $months = 12, string $frequency = 'monthly'): Collection
    {
        $principal = (float) $loan->principal_amount;
        $rate = (float) $loan->interest_rate / 100;
        $monthlyRate = $rate / 12;

        $payment = $principal * ($monthlyRate / (1 - pow(1 + $monthlyRate, -$months)));
        $collection = collect();
        $remaining = $principal;

        for ($i = 1; $i <= $months; $i++) {
            $interest = $remaining * $monthlyRate;
            $principalPortion = $payment - $interest;
            $remaining = max(0, $remaining - $principalPortion);

            $entry = new \stdClass;
            $entry->loan_id = $loan->id;
            $entry->due_date = now()->addMonths($i)->toDateString();
            $entry->amount = (float) number_format($payment, 2, '.', '');
            $entry->principal_amount = (float) number_format($principalPortion, 2, '.', '');
            $entry->interest_amount = (float) number_format($interest, 2, '.', '');
            $entry->frequency = $frequency;
            $entry->status = 'upcoming';

            $collection->push($entry);
        }

        return $collection;
    }
}
