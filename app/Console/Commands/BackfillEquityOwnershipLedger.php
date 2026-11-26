<?php

namespace App\Console\Commands;

use App\Models\EquityInvestment;
use App\Models\EquityOwnershipTransaction;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEquityOwnershipLedger extends Command
{
    protected $signature = 'equity:backfill-ownership-ledger {--round= : Only backfill one equity round ID}';
    protected $description = 'Backfill missing initial-purchase ownership ledger entries for completed, paid equity investments.';

    public function handle(): int
    {
        $query = EquityInvestment::query()->where('status', 'completed');
        if ($this->option('round')) {
            $query->where('equity_round_id', (int) $this->option('round'));
        }

        $created = 0;
        $skipped = 0;

        foreach ($query->orderBy('id')->cursor() as $investment) {
                    
        $reference = 'equity-investment-' . $investment->id . '-ownership';

        // Skip if this investment already has an initial ownership
        // transaction, whether original or previously backfilled.
        $alreadyRecorded = EquityOwnershipTransaction::query()
            ->where('equity_investment_id', $investment->id)
            ->whereIn('transaction_type', [
                'initial_purchase',
                'initial_purchase_backfill',
            ])
            ->exists();

        // Also skip if this exact backfill reference already exists.
        $backfillReferenceExists = EquityOwnershipTransaction::query()
            ->where('reference', $reference)
            ->exists();

        if ($alreadyRecorded || $backfillReferenceExists) {
            $skipped++;
            continue;
        }


            $payment = Payment::query()
                ->where('investment_id', $investment->id)
                ->where('purpose', 'equity_investment')
                ->where('status', 'successful')
                ->orderBy('updated_at')
                ->first();

            if (! $payment) {
                $this->warn("Skipped investment {$investment->id}: no successful equity payment found.");
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($investment, $payment, $reference) {
                EquityOwnershipTransaction::query()->firstOrCreate(
                    ['reference' => $reference],
                    [
                        'investor_id' => $investment->investor_id,
                        'equity_round_id' => $investment->equity_round_id,
                        'equity_investment_id' => $investment->id,
                        'counterparty_investor_id' => null,
                        'transaction_type' => 'initial_purchase_backfill',
                        'units_delta' => (string) $investment->units,
                        'unit_price' => (string) $investment->round->price_per_unit,
                        'total_amount' => (string) $investment->amount,
                        'currency' => $payment->currency ?: 'NGN',
                        'occurred_at' => $payment->updated_at,
                        'metadata' => [
                            'backfilled' => true,
                            'payment_id' => $payment->id,
                            'payment_reference' => $payment->provider_reference,
                            'note' => 'Historical initial purchase ledger entry; current holding was not modified.',
                        ],
                    ],
                );
            }, 3);

            $created++;
            $this->info("Backfilled investment {$investment->id}.");
        }

        $this->info("Complete. Created: {$created}; skipped: {$skipped}.");
        $this->warn('This command writes ownership history only. It does not change current equity_holdings. Reconcile each round before dividend calculations.');

        return self::SUCCESS;
    }
}
