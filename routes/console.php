<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
