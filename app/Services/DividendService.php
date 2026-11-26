<?php

namespace App\Services;

use App\Models\DividendAllocation;
use App\Models\DividendDeclaration;
use App\Models\EquityHolding;
use App\Models\EquityOwnershipTransaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DividendService
{
    public function __construct(private WalletService $walletService) {}

    /** Mark a draft declaration as formally declared by an authorized user. */
    public function declare(DividendDeclaration $declaration, User $actor): DividendDeclaration
    {
        return DB::transaction(function () use ($declaration, $actor) {
            $locked = DividendDeclaration::query()->lockForUpdate()->findOrFail($declaration->id);

            if ($locked->status !== 'draft') {
                throw new RuntimeException('Only a draft dividend can be declared.');
            }
            if ($locked->currency !== 'NGN') {
                throw new RuntimeException('Dividend wallet payments currently support NGN only.');
            }
            if (BigDecimal::of((string) $locked->total_amount)->isLessThanOrEqualTo('0')) {
                throw new RuntimeException('The dividend pool must be greater than zero.');
            }

            $locked->forceFill([
                'status' => 'declared',
                'declared_by' => $actor->id,
                'declared_at' => now(),
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    /**
     * Calculate eligibility from ownership ledger events through the record date.
     * Existing holdings and the all-time ledger must reconcile before calculation.
     * The date must be in the past so that the full record date has elapsed.
     */
    public function calculateAllocations(DividendDeclaration $declaration): DividendDeclaration
    {
        return DB::transaction(function () use ($declaration) {
            $locked = DividendDeclaration::query()->lockForUpdate()->findOrFail($declaration->id);

            if ($locked->status !== 'declared') {
                throw new RuntimeException('Dividend must be declared before allocations are calculated.');
            }
            if (Carbon::parse($locked->record_date)->toDateString() >= now()->toDateString()) {
                throw new RuntimeException('Wait until the record date has fully passed before calculating allocations.');
            }
            if ($locked->currency !== 'NGN') {
                throw new RuntimeException('Dividend wallet payments currently support NGN only.');
            }

            $this->assertCurrentOwnershipLedgerReconciles((int) $locked->equity_round_id);

            $cutoff = Carbon::parse($locked->record_date)->endOfDay();
            $ledger = EquityOwnershipTransaction::query()
                ->where('equity_round_id', $locked->equity_round_id)
                ->where('occurred_at', '<=', $cutoff)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get(['investor_id', 'units_delta']);

            $unitsByInvestor = [];
            foreach ($ledger as $entry) {
                $id = (int) $entry->investor_id;
                $unitsByInvestor[$id] = ($unitsByInvestor[$id] ?? BigDecimal::zero())
                    ->plus((string) $entry->units_delta);
            }

            $eligible = [];
            $totalUnits = BigDecimal::zero();
            foreach ($unitsByInvestor as $investorId => $units) {
                if ($units->isLessThan('0')) {
                    throw new RuntimeException("Ownership ledger is negative for investor {$investorId}; reconcile before declaring dividends.");
                }
                if ($units->isGreaterThan('0')) {
                    $units = $units->toScale(6, RoundingMode::Unnecessary);
                    $eligible[$investorId] = $units;
                    $totalUnits = $totalUnits->plus($units);
                }
            }

            if ($totalUnits->isLessThanOrEqualTo('0')) {
                throw new RuntimeException('No investors held eligible units on the record date.');
            }

            // Integer minor-unit allocation using largest remainder ensures exact reconciliation.
            $poolCents = BigDecimal::of((string) $locked->total_amount)
                ->multipliedBy('100')
                ->toScale(0, RoundingMode::Unnecessary)
                ->toBigInteger();
            $totalUnitMicros = $this->unitsToMicros($totalUnits);

            $shares = [];
            $allocatedCents = BigInteger::zero();
            foreach ($eligible as $investorId => $units) {
                $unitMicros = $this->unitsToMicros($units);
                [$baseCents, $remainder] = $poolCents
                    ->multipliedBy($unitMicros)
                    ->quotientAndRemainder($totalUnitMicros);

                $shares[] = [
                    'investor_id' => $investorId,
                    'units' => $units,
                    'cents' => $baseCents,
                    'remainder' => $remainder,
                ];
                $allocatedCents = $allocatedCents->plus($baseCents);
            }

            $remainingCents = $poolCents->minus($allocatedCents)->toInt();
            usort($shares, function (array $a, array $b): int {
                $remainderOrder = $b['remainder']->compareTo($a['remainder']);
                return $remainderOrder !== 0
                    ? $remainderOrder
                    : ($a['investor_id'] <=> $b['investor_id']);
            });

            for ($i = 0; $i < $remainingCents; $i++) {
                $shares[$i]['cents'] = $shares[$i]['cents']->plus(1);
            }

            if ($locked->allocations()->exists()) {
                throw new RuntimeException('Allocations already exist for this declaration. Do not recalculate or overwrite financial records.');
            }

            foreach ($shares as $share) {
                $amount = BigDecimal::of($share['cents'])
                    ->dividedBy('100', 2, RoundingMode::Unnecessary)
                    ->toScale(2)
                    ->__toString();

                $locked->allocations()->create([
                    'investor_id' => $share['investor_id'],
                    'eligible_units' => (string) $share['units'],
                    'amount' => $amount,
                    'status' => 'pending',
                    'metadata' => ['record_date' => Carbon::parse($locked->record_date)->toDateString()],
                ]);
            }

            $locked->forceFill([
                'status' => 'allocations_calculated',
                'total_eligible_units' => (string) $totalUnits->toScale(6),
                'total_allocated' => (string) $locked->total_amount,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    /** Approve the immutable allocation list before any wallet credit. */
    public function approve(DividendDeclaration $declaration, User $approver): DividendDeclaration
    {
        return DB::transaction(function () use ($declaration, $approver) {
            $locked = DividendDeclaration::query()->lockForUpdate()->findOrFail($declaration->id);

            if ($locked->status !== 'allocations_calculated') {
                throw new RuntimeException('Only a declaration with calculated allocations can be approved.');
            }
            if ((int) $locked->declared_by === (int) $approver->id) {
                throw new RuntimeException('The person who declared the dividend cannot approve the same declaration.');
            }
            if (! $locked->allocations()->exists()) {
                throw new RuntimeException('There are no allocations to approve.');
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    /** Pay all approved allocations. Each allocation is independently retryable. */
    public function payApprovedDeclaration(DividendDeclaration $declaration): DividendDeclaration
    {
        $locked = DB::transaction(function () use ($declaration) {
            $record = DividendDeclaration::query()->lockForUpdate()->findOrFail($declaration->id);
            if (! in_array($record->status, ['approved', 'processing'], true)) {
                throw new RuntimeException('Dividend must be approved before payment.');
            }
            $record->forceFill([
                'status' => 'processing',
                'payment_started_at' => $record->payment_started_at ?? now(),
            ])->save();
            return $record;
        }, 3);

        $allocationIds = $locked->allocations()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id')
            ->pluck('id');

        foreach ($allocationIds as $allocationId) {
            try {
                $this->payAllocation((int) $allocationId);
            } catch (\Throwable $exception) {
                DividendAllocation::query()->whereKey($allocationId)
                    ->whereNotIn('status', ['paid'])
                    ->update([
                        'status' => 'failed',
                        'failure_reason' => mb_substr($exception->getMessage(), 0, 60000),
                        'updated_at' => now(),
                    ]);
            }
        }

        return DB::transaction(function () use ($locked) {
            $record = DividendDeclaration::query()->lockForUpdate()->findOrFail($locked->id);
            $unpaid = $record->allocations()->where('status', '!=', 'paid')->exists();
            if (! $unpaid) {
                $record->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            }
            return $record->refresh();
        }, 3);
    }

    private function payAllocation(int $allocationId): DividendAllocation
    {
        return DB::transaction(function () use ($allocationId) {
            $allocation = DividendAllocation::query()->lockForUpdate()->findOrFail($allocationId);
            $declaration = DividendDeclaration::query()->lockForUpdate()
                ->findOrFail($allocation->dividend_declaration_id);

            if ($allocation->status === 'paid') {
                return $allocation;
            }
            if (! in_array($declaration->status, ['approved', 'processing'], true)) {
                throw new RuntimeException('This dividend is not approved for payment.');
            }
            if ($declaration->currency !== 'NGN') {
                throw new RuntimeException('Only NGN dividend wallet credits are currently supported.');
            }

            $amount = BigDecimal::of((string) $allocation->amount);
            if ($amount->isLessThanOrEqualTo('0')) {
                // A zero-naira allocation is recorded as settled, without a zero-value wallet transaction.
                $allocation->forceFill([
                    'status' => 'paid',
                    'payment_reference' => null,
                    'paid_at' => now(),
                    'failure_reason' => null,
                    'metadata' => array_merge($allocation->metadata ?? [], ['settlement_note' => 'Rounded allocation was below one naira cent; no wallet credit required.']),
                ])->save();
                return $allocation->refresh();
            }

            $investor = User::query()->findOrFail($allocation->investor_id);
            $reference = 'dividend-allocation-' . $allocation->id;
            $transaction = $this->walletService->credit(
                $investor,
                (string) $amount,
                'dividend',
                $reference,
                'Dividend payment: ' . $declaration->title,
                $allocation,
                [
                    'dividend_declaration_id' => $declaration->id,
                    'dividend_allocation_id' => $allocation->id,
                    'equity_round_id' => $declaration->equity_round_id,
                    'record_date' => Carbon::parse($declaration->record_date)->toDateString(),
                ],
            );

            $allocation->forceFill([
                'status' => 'paid',
                'wallet_transaction_id' => $transaction->id,
                'payment_reference' => $reference,
                'paid_at' => now(),
                'failure_reason' => null,
            ])->save();

            return $allocation->refresh();
        }, 3);
    }

    private function assertCurrentOwnershipLedgerReconciles(int $roundId): void
    {
        $current = EquityHolding::query()
            ->where('equity_round_id', $roundId)
            ->get(['investor_id', 'units'])
            ->keyBy(fn (EquityHolding $holding) => (int) $holding->investor_id);

        $ledger = EquityOwnershipTransaction::query()
            ->where('equity_round_id', $roundId)
            ->get(['investor_id', 'units_delta'])
            ->groupBy(fn (EquityOwnershipTransaction $entry) => (int) $entry->investor_id);

        $investorIds = array_unique(array_merge(
            $current->keys()->all(),
            $ledger->keys()->all(),
        ));

        foreach ($investorIds as $investorId) {
            $holdingUnits = BigDecimal::of((string) ($current->get($investorId)?->units ?? '0'));
            $ledgerUnits = BigDecimal::zero();
            foreach ($ledger->get($investorId, collect()) as $entry) {
                $ledgerUnits = $ledgerUnits->plus((string) $entry->units_delta);
            }

            if (! $holdingUnits->isEqualTo($ledgerUnits)) {
                throw new RuntimeException(
                    "Ownership history does not reconcile for investor {$investorId} in equity round {$roundId}. "
                    . 'Backfill and reconcile initial investment ownership transactions before calculating dividends.'
                );
            }
        }
    }

    private function unitsToMicros(BigDecimal $units): BigInteger
    {
        return $units->toScale(6, RoundingMode::Unnecessary)
            ->multipliedBy('1000000')
            ->toScale(0, RoundingMode::Unnecessary)
            ->toBigInteger();
    }
}
