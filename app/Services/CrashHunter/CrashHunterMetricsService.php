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
use Illuminate\Support\Carbon;

final class CrashHunterMetricsService
{
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

        $witness = CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->latest('received_at')
            ->first();

        $snapshotCount = CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->count();

        return [
            'summary' => [
                'hostname' => $meta['hostname'] ?? $server->hostname,
                'version' => $meta['version'] ?? null,
                'last_metrics_at' => $meta['last_metrics_at'] ?? null,
                'incident_mode' => (bool) ($meta['incident_mode'] ?? false),
                'witness_status' => $witness?->status ?? ($meta['witness_status'] ?? 'unknown'),
                'witness_last_at' => $witness?->received_at?->toIso8601String(),
                'ring_count' => $meta['ring_count'] ?? null,
                'snapshots_in_window' => $snapshotCount,
                'metrics_in_window' => $metrics->count(),
                'critical_events_24h' => CrashHunterEvent::query()
                    ->where('server_id', $server->id)
                    ->where('severity', 'critical')
                    ->where('detected_at', '>=', now()->subDay())
                    ->count(),
            ],
            'charts' => $this->buildCharts($metrics),
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
            ])->all(),
            'reports' => $reports->map(fn (CrashHunterReport $r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'trigger_type' => $r->trigger_type,
                'generated_at' => $r->generated_at?->toIso8601String(),
                'verdict' => $r->report_json['diagnosis']['verdict'] ?? null,
            ])->all(),
            'latest_collectors' => $this->latestCollectors($server),
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
}
