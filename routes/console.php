<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('app:post-monthly-interest')->monthlyOn(1, '00:00');
\Illuminate\Support\Facades\Schedule::command('app:auto-draft-dividends')->dailyAt('00:05');
\Illuminate\Support\Facades\Schedule::command('loans:update-aging')->dailyAt('00:00');
