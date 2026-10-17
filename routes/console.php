
<?php

use App\Jobs\PrepareDueRepaymentPayouts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for due repayments every day at 12:05 AM.
Schedule::job(new PrepareDueRepaymentPayouts())
    ->dailyAt('00:05')
    ->withoutOverlapping();
