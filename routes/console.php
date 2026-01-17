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

Schedule::command('events:send-reminders')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/events-send-reminders.log'));

