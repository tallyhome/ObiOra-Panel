<?php

declare(strict_types=1);

use App\Console\Commands\BroadcastDashboardMetricsCommand;
use App\Console\Commands\EvaluateAlertPoliciesCommand;
use App\Console\Commands\EvaluateServerMetricsHealthCommand;
use App\Console\Commands\ExpireDemoAccountsCommand;
use App\Console\Commands\MonitorServersPingCommand;
use App\Console\Commands\RunMonitorsCommand;
use App\Console\Commands\SendMonitoringAlertsCommand;
use App\Jobs\CrashAnalyzer\PruneOldMetricsJob;
use App\Jobs\CrashHunter\PruneOldCrashHunterDataJob;
use App\Jobs\PruneOldMonitorChecksJob;
use App\Jobs\PruneOldServerPingSamplesJob;
use App\Jobs\PruneOldServerMetricSamplesJob;
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

Schedule::command(RunMonitorsCommand::class)->everyMinute();
Schedule::command(EvaluateServerMetricsHealthCommand::class)->everyMinute();
Schedule::command(EvaluateAlertPoliciesCommand::class)->everyMinute();

Schedule::job(new PruneOldMonitorChecksJob)->dailyAt('04:00');
Schedule::job(new PruneOldServerMetricSamplesJob)->dailyAt('04:15');
Schedule::job(new PruneOldServerPingSamplesJob)->dailyAt('04:30');

Schedule::job(new PruneOldMetricsJob)->dailyAt('03:30');
Schedule::job(new PruneOldCrashHunterDataJob)->dailyAt('03:45');

if ((bool) config('obiora.realtime.enabled', false)) {
    $metricsInterval = max(3, (int) config('obiora.realtime.metrics_interval_seconds', 5));
    if ($metricsInterval <= 5) {
        Schedule::command(BroadcastDashboardMetricsCommand::class)->everyFiveSeconds();
    } else {
        Schedule::command(BroadcastDashboardMetricsCommand::class)->everyTenSeconds();
    }
}
