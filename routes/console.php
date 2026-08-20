<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('library:news-sweep')
    ->everyMinute()
    ->withoutOverlapping(10);

Artisan::command('news:publish-scheduled', function (): int {
    return $this->call('library:news-sweep');
})->purpose('Backward-compatible alias for library:news-sweep');

Schedule::command('library:circulation-sweep')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

Schedule::command('library:messages-sweep')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

Schedule::command('library:digital-services-sweep')
    ->dailyAt('06:15')
    ->withoutOverlapping(30);

Schedule::command('repository:usage-prune --days='.(int) config('digital_library.repository_usage_retention_days', 1095))
    ->monthlyOn(1, '03:40')
    ->withoutOverlapping(60);

Schedule::command('library:external-resources:health-check')
    ->dailyAt('06:45')
    ->withoutOverlapping(30);

Schedule::command('library:reports-sweep')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('library:external-resources:notifications')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15);

Schedule::command('library:external-resources:prune-events')
    ->dailyAt('07:00')
    ->withoutOverlapping(30);

Schedule::command('library:integrations:dispatch --limit=100')
    ->everyMinute()
    ->withoutOverlapping(10);

// Proposed safe default. The interval remains documented/configurable; cron
// may be adjusted by operations without changing scanner business rules.
Schedule::command('library:data-quality:scan all')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping(360);
