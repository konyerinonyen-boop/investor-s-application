<?php

namespace App\Console\Commands;

use App\Models\EquityHolding;
use App\Models\EquityInvestment;
use App\Models\EquityOwnershipTransaction;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackfillEquityOwnership extends Command
{
    protected $signature = 'equity:backfill-ownership';

    protected $description =
        'Create current equity holdings from verified successful investments';

    public function handle(): int
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        EquityInvestment::query()
            ->where('status', 'completed')
            ->whereHas('payments', function ($query) {
            $query
                ->where('status', 'successful')
                ->where(function ($paymentQuery) {
                    $paymentQuery
                        ->where('purpose', 'equity_investment')
                        ->orWhere(function ($legacyQuery) {
                            $legacyQuery
                                ->whereNull('purpose')
                                ->whereColumn(
                                    'payments.provider_reference',
                                    'equity_investments.payment_reference'
                                );
                        });
                });
        })
            ->chunkById(100, function ($investments) use (
                &$processed,
                &$skipped,
                &$failed
            ) {
                foreach ($investments as $investment) {
                    try {
                        $result = DB::transaction(function () use ($investment) {
                            // Prevent two backfill processes from handling
                            // the same investment simultaneously.
                            $lockedInvestment = EquityInvestment::query()
                                ->whereKey($investment->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $reference = 'initial-equity-investment-'
                                . $lockedInvestment->id;

                            // A previous successful run must not add units
                            // to the holding again.
                            if (
                                EquityOwnershipTransaction::query()
                                    ->where('reference', $reference)
                                    ->exists()
                            ) {
                                return 'skipped';
                            }

                            if ($lockedInvestment->status !== 'completed') {
                                return 'skipped';
                            }

                            // Serialize backfills for investments in the same equity round.
// This also protects creation of a new holding row.
                            $lockedInvestment->round()
                                ->lockForUpdate()
                                ->firstOrFail();

                           $payment = Payment::query()
                            ->where('investment_id', $lockedInvestment->id)
                            ->where('user_id', $lockedInvestment->investor_id)
                            ->where('status', 'successful')
                            ->where(function ($paymentQuery) use ($lockedInvestment) {
                                $paymentQuery
                                    ->where('purpose', 'equity_investment')
                                    ->orWhere(function ($legacyQuery) use ($lockedInvestment) {
                                        $legacyQuery
                                            ->whereNull('purpose')
                                            ->where(
                                                'provider_reference',
                                                $lockedInvestment->payment_reference
                                            );
                                    });
                            })
                            ->where('amount', $lockedInvestment->amount)
                            ->latest('id')
                            ->first();

                            if (! $payment) {
                                return 'skipped';
                            }

                            $units = BigDecimal::of(
                                (string) $lockedInvestment->units
                            );

                            $amount = BigDecimal::of(
                                (string) $lockedInvestment->amount
                            );

                            // Never create ownership from invalid values.
                            if (
                                $units->isLessThanOrEqualTo('0') ||
                                $amount->isLessThanOrEqualTo('0')
                            ) {
                                return 'skipped';
                            }

                            // Only accept a payment that settled the
                            // investment's full recorded amount.
                            if (
                                BigDecimal::of((string) $payment->amount)
                                    ->compareTo($amount) !== 0
                            ) {
                                return 'skipped';
                            }

                            if (blank($payment->currency)) {
                                return 'skipped';
                            }

                            $unitPrice = $amount->dividedBy(
                                $units,
                                6,
                                RoundingMode::HalfUp
                            );

                            $holding = EquityHolding::query()
                                ->where('investor_id', $lockedInvestment->investor_id)
                                ->where('equity_round_id', $lockedInvestment->equity_round_id)
                                ->lockForUpdate()
                                ->first();

                            if (! $holding) {
                                $holding = EquityHolding::create([
                                    'investor_id' => $lockedInvestment->investor_id,
                                    'equity_round_id' => $lockedInvestment->equity_round_id,
                                    'units' => '0.000000',
                                ]);
                            }

                            // Record the immutable ownership event.
                            EquityOwnershipTransaction::create([
                                'investor_id' => $lockedInvestment->investor_id,
                                'equity_round_id' => $lockedInvestment->equity_round_id,
                                'equity_investment_id' => $lockedInvestment->id,
                                'counterparty_investor_id' => null,
                                'transaction_type' => 'initial_purchase',
                                'units_delta' => $units->toScale(6)->__toString(),
                                'unit_price' => $unitPrice->__toString(),
                                'total_amount' => $amount->toScale(2)->__toString(),
                                'currency' => $payment->currency,
                                'reference' => $reference,
                                'occurred_at' => $payment->updated_at
                                    ?? $payment->created_at
                                    ?? now(),
                                'metadata' => [
                                    'source' => 'ownership_backfill',
                                    'payment_id' => $payment->id,
                                    'payment_provider' => $payment->provider,
                                    'payment_reference' => $payment->provider_reference,
                                ],
                            ]);

                            // Add the acquired units to the investor's
                            // current holding.
                            $holding->units = BigDecimal::of(
                                (string) $holding->units
                            )
                                ->plus($units)
                                ->toScale(6)
                                ->__toString();

                            $holding->save();

                            return 'processed';
                        }, 3);

                        if ($result === 'processed') {
                            $processed++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;

                        report($exception);

                        $this->error(
                            "Investment #{$investment->id} failed: "
                            . $exception->getMessage()
                        );
                    }
                }
            });

        $this->newLine();
        $this->info("Ownership records created: {$processed}");
        $this->comment("Investments skipped: {$skipped}");
        $this->error("Failures: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}


