<?php

use App\Jobs\CheckDeviationAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deviation:check {--year= : Tahun anggaran} {--month= : Bulan}', function () {
    $year = $this->option('year') ?? (int) date('Y');
    $month = $this->option('month') ?? (int) date('n');

    $this->info("Checking deviation alerts for {$month}/{$year}...");

    CheckDeviationAlerts::dispatch((int) $year, (int) $month);

    $this->info('Deviation check job dispatched!');
})->purpose('Check for budget deviation alerts');

Schedule::job(new CheckDeviationAlerts())->dailyAt('08:00')->description('Check daily deviation alerts');
Schedule::job(new CheckDeviationAlerts())->weeklyOn(1, '09:00')->description('Weekly deviation alerts summary');
