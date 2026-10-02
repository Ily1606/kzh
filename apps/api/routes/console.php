<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync buffered plugin views from Redis to Database
Schedule::command('plugins:sync-views')
    ->cron(config('plugins.sync_views_schedule'))
    ->withoutOverlapping();

// Calculate and cache trending plugins
Schedule::command('plugins:refresh-trending')
    ->cron(config('plugins.trending.schedule'))
    ->withoutOverlapping();
