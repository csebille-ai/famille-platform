<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('news:import-rss')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/news-import-rss.log'));

Schedule::call(function () {
    try {
        Cache::put('ops.scheduler.heartbeat_at', now()->toIso8601String(), now()->addDays(7));
    } catch (\Throwable) {
        // ignore
    }

    echo '[' . now()->format('Y-m-d H:i:s') . "] scheduler heartbeat\n";
})
    ->name('ops:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(1)
    ->appendOutputTo(storage_path('logs/scheduler-heartbeat.log'));

Schedule::command('events:send-reminders')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/events-send-reminders.log'));

