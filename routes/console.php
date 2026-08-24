<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly safety net: back up the SQLite database and verify cache integrity.
Schedule::command('clinic:backup')->dailyAt('23:30');
Schedule::command('clinic:reconcile')->dailyAt('23:45');
