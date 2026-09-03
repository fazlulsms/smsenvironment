<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send client reassessment reminders once a day. Idempotent — safe to run daily.
Schedule::command('smsea:send-reassessment-reminders')->dailyAt('08:00');
