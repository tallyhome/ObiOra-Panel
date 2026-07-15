<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CrashAnalyzer\PruneOldMetricsJob;
use App\Jobs\CrashHunter\PruneOldCrashHunterDataJob;
use App\Jobs\PruneOldMonitorChecksJob;
use App\Jobs\PruneOldServerMetricSamplesJob;
use App\Jobs\PruneOldServerPingSamplesJob;
use App\Models\MonitorCheck;
use App\Models\ServerMetricSample;
use App\Models\ServerPingSample;
use Illuminate\Console\Command;

final class ObioraPruneCommand extends Command
{
    protected $signature = 'obiora:prune {--dry-run : Afficher les volumes sans supprimer}';

    protected $description = 'Purge les données monitoring expirées (métriques, checks, ping)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tasks = [
            'monitor_checks' => [
                'cutoff' => now()->subDays(max(7, (int) config('monitoring.check_retention_days', 60))),
                'count' => fn () => MonitorCheck::query()->where('checked_at', '<', now()->subDays(max(7, (int) config('monitoring.check_retention_days', 60))))->count(),
                'run' => fn () => (new PruneOldMonitorChecksJob)->handle(),
            ],
            'server_metric_samples' => [
                'cutoff' => now()->subDays(max(14, (int) config('monitoring.sample_retention_days', 60))),
                'count' => fn () => ServerMetricSample::query()->where('sampled_at', '<', now()->subDays(max(14, (int) config('monitoring.sample_retention_days', 60))))->count(),
                'run' => fn () => (new PruneOldServerMetricSamplesJob)->handle(),
            ],
            'server_ping_samples' => [
                'cutoff' => now()->subDays(max(7, (int) config('monitoring.retention_days', 60))),
                'count' => fn () => ServerPingSample::query()->where('sampled_at', '<', now()->subDays(max(7, (int) config('monitoring.retention_days', 60))))->count(),
                'run' => fn () => (new PruneOldServerPingSamplesJob)->handle(),
            ],
            'crash_analyzer_metrics' => [
                'cutoff' => now()->subHours(max(24, (int) config('crash_analyzer.retention_hours', 72))),
                'count' => fn () => 0,
                'run' => fn () => (new PruneOldMetricsJob)->handle(),
            ],
            'crash_hunter_data' => [
                'cutoff' => now()->subHours(max(24, (int) config('crash_hunter.metrics_retention_hours', 72))),
                'count' => fn () => 0,
                'run' => fn () => (new PruneOldCrashHunterDataJob)->handle(),
            ],
        ];

        foreach ($tasks as $label => $task) {
            $count = $task['count']();

            if ($dryRun) {
                $this->line(sprintf('  %s : ~%d lignes avant %s', $label, $count, $task['cutoff']->format('Y-m-d H:i')));

                continue;
            }

            $task['run']();
            $this->components->info("Purge {$label} terminée.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Mode dry-run — aucune suppression effectuée.');
        }

        return self::SUCCESS;
    }
}
