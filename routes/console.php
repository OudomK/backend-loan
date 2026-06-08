<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:post-monthly-interest')->monthlyOn(1, '00:00');
Schedule::command('app:auto-draft-dividends')->dailyAt('00:05');
Schedule::command('loans:update-aging')->dailyAt('00:00');
Schedule::command('app:cache-dashboard-stats')->everyFiveMinutes();
