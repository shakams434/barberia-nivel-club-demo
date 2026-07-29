<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:dispatch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:work database --stop-when-empty --tries=3 --max-time=240')
    ->everyFiveMinutes()
    ->withoutOverlapping();
