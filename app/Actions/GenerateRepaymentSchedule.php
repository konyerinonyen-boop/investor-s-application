<?php

namespace App\Actions;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class GenerateRepaymentSchedule
{
    public function handle(
        Loan $loan,
        ?int $months = null,
        string $frequency = 'monthly'
    ): Collection {
        $months ??= (int) $loan->term_months;

        if ($months < 1) {
            throw new InvalidArgumentException(
                'The loan term must be at least one month.'
            );
        }

        if (! in_array($frequency, ['monthly', 'quarterly'], true)) {
            throw new InvalidArgumentException(
                'Frequency must be monthly or quarterly.'
            );
        }

        $monthsPerInstallment = $frequency === 'monthly' ? 1 : 3;

        if ($months % $monthsPerInstallment !== 0) {
            throw new InvalidArgumentException(
                'The loan term must be divisible by the repayment frequency.'
            );
        }

        return DB::transaction(function () use (
            $loan,
            $months,
            $frequency,
            $monthsPerInstallment
        ): Collection {
            $loan = Loan::query()
                ->whereKey($loan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->funded_at === null) {
                throw new RuntimeException(
                    'The repayment schedule can only be generated after the loan is funded.'
                );
            }

            // Prevent duplicate installments if this action runs again.
            $existingSchedules = $loan->schedules()
                ->orderBy('due_date')
                ->get();

            if ($existingSchedules->isNotEmpty()) {
                return $existingSchedules;
            }

            $principal = BigDecimal::of(
                (string) $loan->principal_amount
            );

            // The loan interest_rate is treated as an annual percentage.
            $annualRate = BigDecimal::of(
                (string) $loan->interest_rate
            );

            // Interest-only repayment:
            // principal × annual rate × installment months / 1200.
            $interest = $principal
                ->multipliedBy($annualRate)
                ->multipliedBy((string) $monthsPerInstallment)
                ->dividedBy('1200', 8, RoundingMode::HalfUp)
                ->toScale(2, RoundingMode::HalfUp);

            $numberOfInstallments = intdiv(
                $months,
                $monthsPerInstallment
            );

            $baseDate = $loan->funded_at->copy();

            $schedules = collect();

            for ($i = 1; $i <= $numberOfInstallments; $i++) {
                $isFinalInstallment = $i === $numberOfInstallments;

                // Return the full principal in the final installment.
                $principalPortion = $isFinalInstallment
                    ? $principal->toScale(2, RoundingMode::HalfUp)
                    : BigDecimal::zero()->toScale(2);

                $amount = $interest
                    ->plus($principalPortion)
                    ->toScale(2, RoundingMode::HalfUp);

                $dueDate = $baseDate
                    ->copy()
                    ->addMonthsNoOverflow(
                        $i * $monthsPerInstallment
                    )
                    ->toDateString();

                $schedule = $loan->schedules()->create([
                    'due_date' => $dueDate,
                    'amount' => (string) $amount,
                    'principal_amount' => (string) $principalPortion,
                    'interest_amount' => (string) $interest,
                    'frequency' => $frequency,
                    'status' => 'pending',
                ]);

                $schedules->push($schedule);
            }

            return $schedules;
        });
    }
}
