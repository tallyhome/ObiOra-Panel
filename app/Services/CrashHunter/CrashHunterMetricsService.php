<?php

declare(strict_types=1);

namespace App\Services\CrashHunter;

use App\Models\CrashHunterEvent;
use App\Models\CrashHunterIncident;
use App\Models\CrashHunterMetric;
use App\Models\CrashHunterReport;
use App\Models\CrashHunterSnapshot;
use App\Models\CrashHunterWitness;
use App\Models\Server;
use App\Support\DiagnosticConfidence;

final class CrashHunterMetricsService
{
    private const DOCTOR_CHART_POINT_LIMIT = 120;

    /**
     * Vue allégée Doctor & Suite — évite de charger des milliers de métriques.
     *
     * @return array<string, mixed>
     */
    public function doctorDashboard(Server $server, int $minutes = 30): array
    {
        $since = now()->subMinutes($minutes);
        $meta = ($server->metadata ?? [])['crash_hunter'] ?? [];

        $chartMetrics = CrashHunterMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->whereIn('collector', ['cpu', 'system', 'pressure'])
            ->select(['collector', 'sampled_at', 'payload'])
            ->orderByDesc('sampled_at')
            ->limit(360)
            ->get()
            ->sortBy('sampled_at')
            ->values();

        $metricsCount = CrashHunterMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->count();

        $events = CrashHunterEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '>=', $since)
            ->latest('detected_at')
            ->limit(20)
            ->get();

        $incidents = CrashHunterIncident::query()
            ->where('server_id', $server->id)
            ->latest('started_at')
            ->limit(10)
            ->get();

        $reports = CrashHunterReport::query()
            ->where('server_id', $server->id)
            ->latest('generated_at')
            ->limit(5)
            ->get();

        $latestReport = $reports->first();
        $witness = CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->latest('received_at')
            ->first();

        $snapshotCount = CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->count();

        $cpuValues = $chartMetrics->where('collector', 'cpu')->pluck('payload.total_percent')->filter();
        $charts = $this->downsampleCharts($this->buildCharts($chartMetrics));

        return [
            'summary' => [
                'hostname' => $meta['hostname'] ?? $server->hostname,
                'version' => $meta['version'] ?? null,
                'last_metrics_at' => $meta['last_metrics_at'] ?? null,
                'incident_mode' => (bool) ($meta['incident_mode'] ?? false),
                'witness_status' => $witness?->status ?? ($meta['witness_status'] ?? 'unknown'),
                'witness_last_at' => $witness?->received_at?->toIso8601String(),
                'witness_gap_seconds' => $witness?->payload['gap_seconds'] ?? ($meta['witness_gap_seconds'] ?? null),
                'ring_count' => $meta['ring_count'] ?? null,
                'snapshots_in_window' => $snapshotCount,
                'metrics_in_window' => $metricsCount,
                'cpu_avg' => $cpuValues->isNotEmpty() ? round((float) $cpuValues->avg(), 1) : null,
                'cpu_max' => $cpuValues->isNotEmpty() ? round((float) $cpuValues->max(), 1) : null,
                'critical_events_24h' => CrashHunterEvent::query()
                    ->where('server_id', $server->id)
                    ->where('severity', 'critical')
                    ->where('detected_at', '>=', now()->subDay())
                    ->count(),
            ],
            'charts' => $charts,
            'charts_active' => $this->activeChartDefinitions($charts),
            'events' => $events->map(fn (CrashHunterEvent $e) => [
                'event_type' => $e->event_type,
                'severity' => $e->severity,
                'title' => $e->title,
                'details' => $e->details,
                'detected_at' => $e->detected_at?->toIso8601String(),
            ])->all(),
            'incidents' => $incidents->map(fn (CrashHunterIncident $i) => [
                'external_id' => $i->external_id,
                'triggers' => $i->triggers,
                'snapshot_count' => $i->snapshot_count,
                'started_at' => $i->started_at?->toIso8601String(),
                'ended_at' => $i->ended_at?->toIso8601String(),
                'status' => is_array($i->summary) ? ($i->summary['status'] ?? null) : null,
            ])->all(),
            'reports' => $reports->map(fn (CrashHunterReport $r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'trigger_type' => $r->trigger_type,
                'generated_at' => $r->generated_at?->toIso8601String(),
                'verdict' => $r->report_json['diagnosis']['verdict'] ?? null,
                'recommendations_count' => count($r->report_json['recommendations'] ?? []),
            ])->all(),
            'latest_report_insights' => $this->buildReportInsights($latestReport),
            'latest_collectors' => [],
            'snapshots' => $this->recentSnapshots($server, $since, 12),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardData(Server $server, int $minutes = 60): array
    {
        $since = now()->subMinutes($minutes);
        $meta = ($server->metadata ?? [])['crash_hunter'] ?? [];

        $metrics = CrashHunterMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get();

        $events = CrashHunterEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '>=', $since)
            ->latest('detected_at')
            ->limit(50)
            ->get();

        $incidents = CrashHunterIncident::query()
            ->where('server_id', $server->id)
            ->latest('started_at')
            ->limit(10)
            ->get();

        $reports = CrashHunterReport::query()
            ->where('server_id', $server->id)
            ->latest('generated_at')
            ->limit(5)
            ->get();

        $latestReport = $reports->first();
        $witness = CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->latest('received_at')
            ->first();

        $snapshotCount = CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->count();

        $cpuValues = $metrics->where('collector', 'cpu')->pluck('payload.total_percent')->filter();
        $charts = $this->buildCharts($metrics);

        return [
            'summary' => [
                'hostname' => $meta['hostname'] ?? $server->hostname,
                'version' => $meta['version'] ?? null,
                'last_metrics_at' => $meta['last_metrics_at'] ?? null,
                'incident_mode' => (bool) ($meta['incident_mode'] ?? false),
                'witness_status' => $witness?->status ?? ($meta['witness_status'] ?? 'unknown'),
                'witness_last_at' => $witness?->received_at?->toIso8601String(),
                'witness_gap_seconds' => $witness?->payload['gap_seconds'] ?? ($meta['witness_gap_seconds'] ?? null),
                'ring_count' => $meta['ring_count'] ?? null,
                'snapshots_in_window' => $snapshotCount,
                'metrics_in_window' => $metrics->count(),
                'cpu_avg' => $cpuValues->isNotEmpty() ? round((float) $cpuValues->avg(), 1) : null,
                'cpu_max' => $cpuValues->isNotEmpty() ? round((float) $cpuValues->max(), 1) : null,
                'critical_events_24h' => CrashHunterEvent::query()
                    ->where('server_id', $server->id)
                    ->where('severity', 'critical')
                    ->where('detected_at', '>=', now()->subDay())
                    ->count(),
            ],
            'charts' => $charts,
            'charts_active' => $this->activeChartDefinitions($charts),
            'events' => $events->map(fn (CrashHunterEvent $e) => [
                'event_type' => $e->event_type,
                'severity' => $e->severity,
                'title' => $e->title,
                'details' => $e->details,
                'detected_at' => $e->detected_at?->toIso8601String(),
            ])->all(),
            'incidents' => $incidents->map(fn (CrashHunterIncident $i) => [
                'external_id' => $i->external_id,
                'triggers' => $i->triggers,
                'snapshot_count' => $i->snapshot_count,
                'started_at' => $i->started_at?->toIso8601String(),
                'ended_at' => $i->ended_at?->toIso8601String(),
                'status' => is_array($i->summary) ? ($i->summary['status'] ?? null) : null,
            ])->all(),
            'reports' => $reports->map(fn (CrashHunterReport $r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'trigger_type' => $r->trigger_type,
                'generated_at' => $r->generated_at?->toIso8601String(),
                'verdict' => $r->report_json['diagnosis']['verdict'] ?? null,
                'recommendations_count' => count($r->report_json['recommendations'] ?? []),
            ])->all(),
            'latest_report_insights' => $this->buildReportInsights($latestReport),
            'latest_collectors' => $this->latestCollectors($server),
            'snapshots' => $this->recentSnapshots($server, $since),
        ];
    }

    public function pruneOld(Server $server, int $snapshotRetentionHours): int
    {
        $metricsRetention = (int) config('crash_hunter.metrics_retention_hours', 72);
        $metricsCutoff = now()->subHours($metricsRetention);
        $snapshotCutoff = now()->subHours($snapshotRetentionHours);
        $witnessCutoff = now()->subDays(7);

        $deleted = CrashHunterMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '<', $metricsCutoff)
            ->delete();

        $deleted += CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '<', $snapshotCutoff)
            ->delete();

        $deleted += CrashHunterEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '<', $metricsCutoff)
            ->where('severity', '!=', 'critical')
            ->delete();

        CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->where('received_at', '<', $witnessCutoff)
            ->delete();

        return $deleted;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildReportInsights(?CrashHunterReport $report): ?array
    {
        if ($report === null) {
            return null;
        }

        $json = $report->report_json ?? [];
        $reboot = is_array($json['reboot_detection'] ?? null) ? $json['reboot_detection'] : [];
        $diagnosis = is_array($json['diagnosis'] ?? null) ? $json['diagnosis'] : [];
        $chrono = is_array($json['chronological_report'] ?? null) ? $json['chronological_report'] : [];

        return [
            'report_id' => $report->external_id,
            'trigger_type' => $report->trigger_type,
            'generated_at' => $report->generated_at?->toIso8601String(),
            'reboot_detected' => (bool) ($reboot['reboot_detected'] ?? false),
            'reboot_reason' => $reboot['reason'] ?? null,
            'reboot_classification' => $json['reboot_classification']['label'] ?? null,
            'verdict' => $diagnosis['verdict'] ?? null,
            'diagnosis_summary' => $diagnosis['summary'] ?? null,
            'confidence' => $diagnosis['confidence'] ?? null,
            'confidence_display' => DiagnosticConfidence::format($diagnosis['confidence'] ?? null),
            'causal_story' => $chrono['causal_story'] ?? ($json['causal_correlation']['story_text'] ?? null),
            'recommendations' => array_slice($json['recommendations'] ?? [], 0, 10),
            'similar_crashes' => array_slice($json['similar_crashes'] ?? [], 0, 3),
            'ml_prediction' => $json['ml_prediction'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestCollectors(Server $server): array
    {
        $collectors = ['system', 'cpu', 'memory', 'pressure', 'blkmq', 'qemu', 'virtualizor'];
        $result = [];
        foreach ($collectors as $name) {
            $metric = CrashHunterMetric::query()
                ->where('server_id', $server->id)
                ->where('collector', $name)
                ->latest('sampled_at')
                ->first();
            if ($metric !== null) {
                $result[$name] = $metric->payload;
            }
        }

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CrashHunterMetric>  $metrics
     * @return array<string, mixed>
     */
    private function buildCharts($metrics): array
    {
        $cpu = [];
        $load = [];
        $iowait = [];
        $pressureIo = [];

        foreach ($metrics as $metric) {
            $ts = $metric->sampled_at?->timestamp ?? 0;
            if ($metric->collector === 'cpu') {
                $cpu[] = ['t' => $ts, 'v' => $metric->payload['total_percent'] ?? null];
                $iowait[] = ['t' => $ts, 'v' => $metric->payload['iowait_percent'] ?? null];
            }
            if ($metric->collector === 'system') {
                $load[] = ['t' => $ts, 'v' => $metric->payload['load_1'] ?? null];
            }
            if ($metric->collector === 'pressure') {
                $parsed = $metric->payload['parsed']['io']['avg10'] ?? null;
                $pressureIo[] = ['t' => $ts, 'v' => $parsed];
            }
        }

        return [
            'cpu' => $cpu,
            'load' => $load,
            'iowait' => $iowait,
            'pressure_io' => $pressureIo,
        ];
    }

    /**
     * @param  array<string, list<array{t: int, v: mixed}>>  $charts
     * @return array<string, list<array{t: int, v: mixed}>>
     */
    private function downsampleCharts(array $charts, int $maxPoints = self::DOCTOR_CHART_POINT_LIMIT): array
    {
        foreach ($charts as $key => $series) {
            if (! is_array($series) || count($series) <= $maxPoints) {
                continue;
            }

            $step = (int) ceil(count($series) / $maxPoints);
            $charts[$key] = array_values(array_filter(
                $series,
                static fn (mixed $_, int $index) => $index % $step === 0,
                ARRAY_FILTER_USE_BOTH,
            ));
        }

        return $charts;
    }

    /**
     * Graphiques ApexCharts à afficher (séries avec au moins un point non null).
     *
     * @param  array<string, list<array{t: int, v: mixed}>>  $charts
     * @return list<array{id: string, title: string, series: list<array{t: int, v: mixed}>, color: string, col: int}>
     */
    public function activeChartDefinitions(array $charts): array
    {
        $definitions = [
            ['id' => 'ch-hunter-cpu', 'title' => 'CPU %', 'key' => 'cpu', 'color' => '#ef4444', 'col' => 6],
            ['id' => 'ch-hunter-load', 'title' => 'Load 1m', 'key' => 'load', 'color' => '#3b82f6', 'col' => 6],
            ['id' => 'ch-hunter-iowait', 'title' => 'IOWait %', 'key' => 'iowait', 'color' => '#8b5cf6', 'col' => 6],
            ['id' => 'ch-hunter-psi', 'title' => 'PSI IO avg10', 'key' => 'pressure_io', 'color' => '#f97316', 'col' => 6],
        ];

        $active = [];

        foreach ($definitions as $def) {
            $series = $charts[$def['key']] ?? [];
            if (! is_array($series) || ! $this->seriesHasPlottablePoints($series)) {
                continue;
            }

            $active[] = [
                'id' => $def['id'],
                'title' => $def['title'],
                'series' => $series,
                'color' => $def['color'],
                'col' => $def['col'],
            ];
        }

        return $active;
    }

    /**
     * @param  list<array{t: int, v: mixed}>  $series
     */
    private function seriesHasPlottablePoints(array $series): bool
    {
        foreach ($series as $point) {
            if (is_array($point) && array_key_exists('v', $point) && $point['v'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentSnapshots(Server $server, \Illuminate\Support\Carbon $since, int $limit = 24): array
    {
        return CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->latest('sampled_at')
            ->limit($limit)
            ->get()
            ->map(fn (CrashHunterSnapshot $snapshot) => $this->formatSnapshotSummary($snapshot))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotDetail(Server $server, int $snapshotId): ?array
    {
        $snapshot = CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('id', $snapshotId)
            ->first();

        if ($snapshot === null) {
            return null;
        }

        return [
            'summary' => $this->formatSnapshotSummary($snapshot),
            'payload' => $snapshot->payload ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSnapshotSummary(CrashHunterSnapshot $snapshot): array
    {
        $payload = $snapshot->payload ?? [];

        return [
            'id' => $snapshot->id,
            'slot' => $snapshot->slot,
            'sampled_at' => $snapshot->sampled_at?->toIso8601String(),
            'mode' => $payload['mode'] ?? null,
            'triggers' => is_array($payload['triggers'] ?? null) ? $payload['triggers'] : [],
            'cpu_percent' => data_get($payload, 'cpu.total_percent') ?? data_get($payload, 'system.cpu_percent'),
            'load_1' => data_get($payload, 'system.load_1'),
            'iowait_percent' => data_get($payload, 'cpu.iowait_percent'),
            'memory_percent' => data_get($payload, 'memory.used_percent'),
            'vm_count' => data_get($payload, 'virtualizor.running_vms'),
        ];
    }
}
