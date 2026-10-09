<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

       /**
     * Number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Wait 10 seconds before retrying.
     */
    public int $backoff = 10;
    public function __construct(
        public User $user
    ) {}

    public function handle(): void
    {
        $this->user->sendEmailVerificationNotification();
    }
}