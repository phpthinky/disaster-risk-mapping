<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled tasks ────────────────────────────────────────────────────────
// Annual population snapshot — runs once a year on Dec 31 at 23:55.
// This saves snapshot_type='annual' records for every barangay so staff can
// compare data year-over-year in the Population module archive trail.
//
// To enable: add  * * * * * php /path/to/artisan schedule:run  to the server's crontab.
Schedule::command('population:annual-snapshot')
    ->yearlyOn(12, 31, '23:55')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/population-snapshot.log'));
