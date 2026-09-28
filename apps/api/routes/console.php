<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync buffered plugin views from Redis to Database every 5 minutes
Schedule::command('plugins:sync-views')
    ->everyFiveMinutes()
    ->withoutOverlapping();
