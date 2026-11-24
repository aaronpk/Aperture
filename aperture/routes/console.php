<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue a fetch of every source that is due. Needs `php artisan schedule:run` in cron every minute.
Schedule::command('sources:poll')->everyMinute()->withoutOverlapping(10);
