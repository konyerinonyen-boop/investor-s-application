<?php

namespace App\Jobs;

use App\Models\Loan;
use App\Models\Notification;
use App\Models\RepaymentPayout;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PrepareDueRepaymentPayouts implements ShouldQueue
{
    use Queueable;

    public function handle(WalletService $walletService): void
    {
        RepaymentSchedule::query()
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->whereHas('loan', function ($query) {
                $query->whereNotNull('funded_at')
                    ->whereNotNull('investor_id');
            })
            ->select('id')
            ->chunkById(100, function ($schedules) use ($walletService): void {
                foreach ($schedules as $item) {
                    $payoutId = DB::transaction(function () use (
                        $item,
                        $walletService
                    ): ?int {
                        $schedule = RepaymentSchedule::query()
                            ->whereKey($item->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $schedule || $schedule->status !== 'pending') {
                            return null;
                        }

                        if ($schedule->due_date->isFuture()) {
                            return null;
                        }

                        $loan = Loan::query()
                            ->whereKey($schedule->loan_id)
                            ->lockForUpdate()
                            ->first();

                        if (
                            ! $loan
                            || $loan->funded_at === null
                            || ! $loan->investor_id
                        ) {
                            return null;
                        }

                        $investor = User::find($loan->investor_id);

                        if (! $investor) {
                            return null;
                        }

                        $reference = 'repayment-schedule-'.$schedule->id;

                        // Keep the existing payout record for auditing.
                        // Admin approval is not required for wallet credits.
                        $payout = RepaymentPayout::firstOrCreate(
                            ['repayment_schedule_id' => $schedule->id],
                            [
                                'investor_id' => $investor->id,
                                'amount' => $schedule->amount,
                                'currency' => 'NGN',
                                'approval_status' => 'not_required',
                                'transfer_status' => 'not_started',
                            ]
                        );

                        // This uses your existing WalletService.
                        // The stable reference prevents duplicate credits.
                        $transaction = $walletService->credit(
                            $investor,
                            (string) $schedule->amount,
                            'repayment',
                            $reference,
                            'Automatic repayment for installment #'.$schedule->id,
                            $schedule,
                            [
                                'loan_id' => $loan->id,
                                'repayment_schedule_id' => $schedule->id,
                                'principal_amount' => (string) $schedule->principal_amount,
                                'interest_amount' => (string) $schedule->interest_amount,
                            ],
                        );

                        // The credit and schedule update share one DB transaction.
                        $schedule->forceFill([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'payment_reference' => $reference,
                        ])->save();

                        $payout->forceFill([
                            'investor_id' => $investor->id,
                            'amount' => $schedule->amount,
                            'currency' => 'NGN',
                            'approval_status' => 'not_required',
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                            'review_note' => 'Automatically credited to investor wallet.',
                            'transfer_reference' => $reference,
                            'transfer_status' => 'completed',
                            'provider_response' => [
                                'channel' => 'wallet',
                                'wallet_transaction_id' => $transaction->id,
                            ],
                            'transferred_at' => now(),
                        ])->save();

                        return $payout->id;
                    }, 3);

                    if ($payoutId !== null) {
                        NotifyRepaymentPayoutReview::dispatch($payoutId);
                    }
                }
            });
    }
}
