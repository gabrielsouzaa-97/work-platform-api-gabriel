<?php

use App\Console\Commands\AuditPurgeCommand;
use App\Console\Commands\CleanExpiredWebhookSecretsCommand;
use App\Console\Commands\ClusterHealthCheckCommand;
use App\Console\Commands\CustomersSyncCommand;
use App\Console\Commands\JobsObservabilityCheckCommand;
use App\Console\Commands\JobsPollStuckCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AuditPurgeCommand::class)->monthlyOn(1, '03:30')->withoutOverlapping()->runInBackground();
Schedule::command(ClusterHealthCheckCommand::class)->everyFiveMinutes();
Schedule::command(CleanExpiredWebhookSecretsCommand::class)->dailyAt('03:00');
Schedule::command(CustomersSyncCommand::class)->dailyAt('03:00');
Schedule::command(JobsPollStuckCommand::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(JobsObservabilityCheckCommand::class)->everyFiveMinutes()->withoutOverlapping();
