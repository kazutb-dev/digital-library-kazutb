<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('news:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('library:circulation-sweep')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

// Proposed safe default. The interval remains documented/configurable; cron
// may be adjusted by operations without changing scanner business rules.
Schedule::command('library:data-quality:scan all')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping(360);
