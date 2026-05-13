<?php

use App\Console\Commands\CleanExpiredWebhookSecretsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CleanExpiredWebhookSecretsCommand::class)->dailyAt('03:00');
