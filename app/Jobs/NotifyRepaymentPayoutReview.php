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

        // Only notify users after the wallet credit is recorded.
        if ($payout->transfer_status !== 'completed') {
            return;
        }

        $schedule = $payout->repaymentSchedule;

        if (! $schedule) {
            return;
        }

        $loan = $schedule->loan;

        if (! $loan) {
            return;
        }

        $amount = number_format((float) $payout->amount, 2);
        $reference = $payout->transfer_reference;

        // 1. Notify the investor.
        $investor = User::find($payout->investor_id);

        if ($investor) {
            Notification::firstOrCreate(
                [
                    'user_id' => $investor->id,
                    'type' => 'repayment_wallet_credit',
                    'message' => "Your repayment of NGN {$amount} for installment #{$schedule->id} has been credited to your wallet. Reference: {$reference}.",
                ],
                [
                    'title' => 'Repayment credited to your wallet',
                    'is_read' => false,
                ]
            );
        }

        // 2. Inform administrators. Their approval is not required.
        $admins = User::role([
            'admin',
            'super_admin',
            'operations',
        ])->get();

        foreach ($admins as $admin) {
            Notification::firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'type' => 'repayment_wallet_credit_admin',
                    'message' => "Repayment of NGN {$amount} for installment #{$schedule->id} was automatically credited to investor wallet ID {$payout->investor_id}. Reference: {$reference}.",
                ],
                [
                    'title' => 'Automatic repayment completed',
                    'is_read' => false,
                ]
            );
        }
    }
}
