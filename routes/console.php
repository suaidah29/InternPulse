<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily nudges. Both are idempotent per user per day, so a re-run is harmless.
Schedule::command('logbook:remind')
    ->weekdays()
    ->at('17:30')
    ->timezone('Asia/Kuala_Lumpur');

Schedule::command('tasks:overdue')
    ->weekdays()
    ->at('09:00')
    ->timezone('Asia/Kuala_Lumpur');

// Lets the System health page tell "the scheduler is running" from "nobody set up
// cron". Without this the only symptom of a missing cron entry is nudges that
// silently never arrive. See app/Services/SystemHealth.php.
Schedule::call(fn () => Cache::put('schedule_heartbeat', now()->toIso8601String(), now()->addDay()))
    ->everyFiveMinutes()
    ->description('Record that the scheduler is running');
