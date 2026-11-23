<?php

use App\Console\Commands\PurgeExpiredDocuments;
use App\Console\Commands\ResumePausedDocuments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(PurgeExpiredDocuments::class)->hourly();
Schedule::command(ResumePausedDocuments::class)->dailyAt('00:00')->timezone('Asia/Riyadh');
