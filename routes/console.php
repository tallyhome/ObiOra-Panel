<?php

declare(strict_types=1);

use App\Console\Commands\BroadcastDashboardMetricsCommand;
use App\Console\Commands\ExpireDemoAccountsCommand;
use App\Console\Commands\MonitorServersPingCommand;
use App\Console\Commands\SendMonitoringAlertsCommand;
use App\Jobs\CrashAnalyzer\PruneOldMetricsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$pingInterval = max(15, (int) config('obiora.diagnostics.ping_interval_seconds', 30));

if ($pingInterval <= 30) {
    Schedule::command(MonitorServersPingCommand::class)->everyThirtySeconds();
} else {
    Schedule::command(MonitorServersPingCommand::class)->everyMinute();
}

if (config('obiora.diagnostics.alerts_email', true)) {
    Schedule::command(SendMonitoringAlertsCommand::class)->everyFiveMinutes();
}

Schedule::command(ExpireDemoAccountsCommand::class)->hourly();

Schedule::job(new PruneOldMetricsJob)->dailyAt('03:30');

if ((bool) config('obiora.realtime.enabled', false)) {
    $metricsInterval = max(3, (int) config('obiora.realtime.metrics_interval_seconds', 5));
    if ($metricsInterval <= 5) {
        Schedule::command(BroadcastDashboardMetricsCommand::class)->everyFiveSeconds();
    } else {
        Schedule::command(BroadcastDashboardMetricsCommand::class)->everyTenSeconds();
    }
}
