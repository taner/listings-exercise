<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Instant searches are emailed as their alerts are created. This run is what
// "daily" and "weekly" mean: the alerts gathered since the last run go out in
// one email per buyer, first thing in the morning. The command adds the weekly
// searches on a Monday, so nobody gets more than one digest a day.
Schedule::command('alerts:send-digests')->dailyAt('08:00');
