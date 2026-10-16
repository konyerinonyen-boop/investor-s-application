<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\RepaymentPayout;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyRepaymentPayoutReview implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $payoutId
    ) {}

    public function handle(): void
    {
        $payout = RepaymentPayout::with('repaymentSchedule')
            ->find($this->payoutId);

        if (! $payout) {
            return;
        }

        $reviewers = User::role([
            'admin',
            'super_admin',
            'operations',
        ])->get();

        foreach ($reviewers as $reviewer) {
            Notification::firstOrCreate(
                [
                    'user_id' => $reviewer->id,
                    'type' => 'repayment_payout_review',
                    'message' => "Repayment payout #{$payout->id} requires review.",
                ],
                [
                    'title' => 'Repayment payout requires review',
                    'is_read' => false,
                ]
            );
        }
    }
}