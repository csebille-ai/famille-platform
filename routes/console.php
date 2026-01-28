<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('news:import-rss')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/news-import-rss.log'));

Schedule::command('ops:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(1)
    ->appendOutputTo(storage_path('logs/scheduler-heartbeat.log'));

Schedule::command('events:send-reminders')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/events-send-reminders.log'));

// Queue worker (shared hosting friendly).
// Required for Google Calendar auto-sync (and any queued jobs).
Schedule::command('queue:work --stop-when-empty --max-time=55 --sleep=1 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(1)
    ->appendOutputTo(storage_path('logs/queue-work.log'));

