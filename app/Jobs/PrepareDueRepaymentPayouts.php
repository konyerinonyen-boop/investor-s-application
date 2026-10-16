<?php

namespace App\Jobs;

use App\Jobs\NotifyRepaymentPayoutReview;
use App\Models\RepaymentPayout;
use App\Models\RepaymentSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareDueRepaymentPayouts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
       public function handle(): void
    {
        RepaymentSchedule::query()
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->whereHas('loan', function ($query) {
                $query->whereNotNull('funded_at')
                    ->whereNotNull('investor_id');
            })
            ->with('loan')
            ->chunkById(100, function ($schedules) {
                foreach ($schedules as $schedule) {
                    $payout = RepaymentPayout::firstOrCreate(
                        [
                            'repayment_schedule_id' => $schedule->id,
                        ],
                        [
                            'investor_id' => $schedule->loan->investor_id,
                            'amount' => $schedule->amount,
                            'currency' => 'NGN',
                            'approval_status' => 'pending_review',
                            'transfer_status' => 'not_started',
                        ]
                    );

                    if ($payout->wasRecentlyCreated) {
                        NotifyRepaymentPayoutReview::dispatch(
                            $payout->id
                        );
                    }
                }
            });
    }
}
