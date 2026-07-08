<?php

declare(strict_types=1);

namespace App\Services\CrashAnalyzer;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CrashAnalyzerMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboardData(Server $server, int $minutes = 60): array
    {
        $since = now()->subMinutes($minutes);

        $metrics = CrashAnalyzerMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['collector', 'sampled_at', 'payload']);

        $events = CrashAnalyzerEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '>=', $since)
            ->orderByDesc('detected_at')
            ->limit(200)
            ->get();

        $reports = CrashAnalyzerReport::query()
            ->where('server_id', $server->id)
            ->latest('generated_at')
            ->limit(10)
            ->get(['id', 'external_id', 'trigger_type', 'generated_at', 'hostname']);

        return [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'hostname' => $server->hostname,
            ],
            'charts' => $this->buildChartSeries($metrics),
            'events' => $events->map(fn (CrashAnalyzerEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'severity' => $e->severity,
                'title' => $e->title,
                'details' => $e->details,
                'detected_at' => $e->detected_at?->toIso8601String(),
            ])->values()->all(),
            'reports' => $reports->map(fn (CrashAnalyzerReport $r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'trigger_type' => $r->trigger_type,
                'generated_at' => $r->generated_at?->toIso8601String(),
            ])->values()->all(),
            'summary' => $this->buildSummary($metrics, $events),
        ];
    }

    /**
     * @return Collection<int, CrashAnalyzerMetric>
     */
    public function metricsForExport(Server $server, Carbon $since): Collection
    {
        return CrashAnalyzerMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get();
    }

    /**
     * @param  Collection<int, CrashAnalyzerMetric>  $metrics
     * @return array<string, mixed>
     */
    private function buildChartSeries(Collection $metrics): array
    {
        $cpu = [];
        $memory = [];
        $load = [];
        $disk = [];
        $network = [];

        foreach ($metrics as $metric) {
            $label = $metric->sampled_at?->format('H:i:s') ?? '';
            $payload = $metric->payload ?? [];

            match ($metric->collector) {
                'cpu' => $cpu[] = [
                    'x' => $label,
                    'y' => (float) ($payload['usage_percent'] ?? 0),
                    'load_1' => (float) ($payload['load_1'] ?? 0),
                ],
                'memory' => $memory[] = [
                    'x' => $label,
                    'y' => (float) ($payload['used_percent'] ?? 0),
                ],
                'load' => $load[] = [
                    'x' => $label,
                    'uptime' => (float) ($payload['uptime_seconds'] ?? 0),
                ],
                'disk' => $disk[] = [
                    'x' => $label,
                    'y' => (float) ($payload['io_wait_percent'] ?? 0),
                ],
                'network' => $network[] = [
                    'x' => $label,
                    'connections' => (int) ($payload['tcp_connections'] ?? 0),
                ],
                default => null,
            };
        }

        return compact('cpu', 'memory', 'load', 'disk', 'network');
    }

    /**
     * @param  Collection<int, CrashAnalyzerMetric>  $metrics
     * @param  Collection<int, CrashAnalyzerEvent>  $events
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $metrics, Collection $events): array
    {
        $cpuValues = $metrics->where('collector', 'cpu')
            ->map(fn ($m) => (float) ($m->payload['usage_percent'] ?? 0))
            ->filter();

        $memValues = $metrics->where('collector', 'memory')
            ->map(fn ($m) => (float) ($m->payload['used_percent'] ?? 0))
            ->filter();

        return [
            'metrics_count' => $metrics->count(),
            'events_count' => $events->count(),
            'critical_events' => $events->where('severity', 'critical')->count(),
            'cpu_avg' => $cpuValues->isNotEmpty() ? round($cpuValues->avg(), 1) : null,
            'cpu_max' => $cpuValues->isNotEmpty() ? round($cpuValues->max(), 1) : null,
            'memory_avg' => $memValues->isNotEmpty() ? round($memValues->avg(), 1) : null,
            'memory_max' => $memValues->isNotEmpty() ? round($memValues->max(), 1) : null,
        ];
    }

    public function pruneOld(Server $server, int $retentionHours): int
    {
        $cutoff = now()->subHours($retentionHours);

        $deleted = CrashAnalyzerMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '<', $cutoff)
            ->delete();

        $deleted += CrashAnalyzerEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '<', $cutoff)
            ->where('severity', '!=', 'critical')
            ->delete();

        return $deleted;
    }
}
