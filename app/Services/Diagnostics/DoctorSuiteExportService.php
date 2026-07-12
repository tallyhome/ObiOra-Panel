<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashAnalyzerReport;
use App\Models\CrashHunterEvent;
use App\Models\CrashHunterIncident;
use App\Models\CrashHunterMetric;
use App\Models\CrashHunterReport;
use App\Models\CrashHunterSnapshot;
use App\Models\CrashHunterWitness;
use App\Models\DiagnosticReport;
use App\Models\Server;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DoctorSuiteExportService
{
    public function __construct(
        private readonly DoctorSuiteService $suite,
        private readonly DiagnosticsAgentVersionService $agentVersions,
    ) {}

    public function resolveSince(?int $hours): Carbon
    {
        $maxHours = max(
            (int) config('crash_hunter.metrics_retention_hours', 72),
            (int) ceil(((int) config('crash_analyzer.history_minutes', 60)) / 60),
        );
        $hours = min($maxHours, max(1, $hours ?? 24));

        return now()->subHours($hours);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Server $server, Carbon $since): array
    {
        $historyMinutes = (int) max(1, min(1440, $since->diffInMinutes(now())));
        $overview = $this->suite->serverOverview($server, $historyMinutes, lite: true);

        $doctorReport = DiagnosticReport::query()
            ->where('server_id', $server->id)
            ->latest('generated_at')
            ->first();

        return [
            'exported_at' => now()->toIso8601String(),
            'since' => $since->toIso8601String(),
            'panel_version' => (string) config('obiora.version'),
            'server' => $this->serverPayload($server),
            'agent_versions' => $this->agentVersions->compare($server),
            'plain_summary' => $overview['plain_summary'] ?? null,
            'doctor' => [
                'latest_report' => $doctorReport !== null ? [
                    'id' => $doctorReport->id,
                    'score' => $doctorReport->score,
                    'status' => $doctorReport->status,
                    'doctor_version' => $doctorReport->doctor_version,
                    'generated_at' => $doctorReport->generated_at?->toIso8601String(),
                    'report_json' => $doctorReport->report_json,
                    'critical_findings' => $doctorReport->critical_findings,
                ] : null,
                'overview' => $overview['doctor'] ?? null,
            ],
            'crash_analyzer' => [
                'overview' => $overview['crash_analyzer'] ?? null,
                'metrics' => $this->crashAnalyzerMetrics($server, $since),
                'metrics_total' => ($caMetricsTotal = CrashAnalyzerMetric::query()
                    ->where('server_id', $server->id)
                    ->where('sampled_at', '>=', $since)
                    ->count()),
                'metrics_truncated' => $caMetricsTotal > 8000,
                'events' => $this->crashAnalyzerEvents($server, $since),
                'reports' => $this->crashAnalyzerReports($server, $since),
            ],
            'crash_hunter' => [
                'overview' => $overview['crash_hunter'] ?? null,
                'metrics' => $this->crashHunterMetrics($server, $since),
                'metrics_total' => ($chMetricsTotal = CrashHunterMetric::query()
                    ->where('server_id', $server->id)
                    ->where('sampled_at', '>=', $since)
                    ->count()),
                'metrics_truncated' => $chMetricsTotal > 8000,
                'events' => $this->crashHunterEvents($server, $since),
                'incidents' => $this->crashHunterIncidents($server, $since),
                'reports' => $this->crashHunterReports($server, $since),
                'snapshots' => $this->crashHunterSnapshots($server, $since),
                'latest_witness' => $this->latestWitness($server),
            ],
        ];
    }

    public function exportJson(Server $server, Carbon $since): StreamedResponse
    {
        $payload = $this->buildPayload($server, $since);
        $filename = $this->filename($server, $since, 'json');

        return response()->streamDownload(
            static fn () => print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function exportCsv(Server $server, Carbon $since): StreamedResponse
    {
        $filename = $this->filename($server, $since, 'csv');

        return response()->streamDownload(function () use ($server, $since) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['source', 'sampled_at', 'collector', 'metric', 'value']);

            CrashAnalyzerMetric::query()
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $since)
                ->orderBy('sampled_at')
                ->chunk(500, function ($metrics) use ($handle) {
                    foreach ($metrics as $metric) {
                        $this->writeMetricRows($handle, 'crash_analyzer', $metric->sampled_at?->toIso8601String(), $metric->collector, $metric->payload ?? []);
                    }
                });

            CrashHunterMetric::query()
                ->where('server_id', $server->id)
                ->where('sampled_at', '>=', $since)
                ->orderBy('sampled_at')
                ->chunk(500, function ($metrics) use ($handle) {
                    foreach ($metrics as $metric) {
                        $this->writeMetricRows($handle, 'crash_hunter', $metric->sampled_at?->toIso8601String(), $metric->collector, $metric->payload ?? []);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function exportHtml(Server $server, Carbon $since): \Illuminate\Http\Response
    {
        $payload = $this->buildPayload($server, $since);

        return response()->view('diagnostics.exports.doctor-suite', [
            'server' => $server,
            'since' => $since,
            'payload' => $payload,
        ], 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($server, $since, 'html').'"',
        ]);
    }

    public function viewHtml(Server $server, Carbon $since): \Illuminate\Http\Response
    {
        $payload = $this->buildPayload($server, $since);

        return response()->view('diagnostics.exports.doctor-suite', [
            'server' => $server,
            'since' => $since,
            'payload' => $payload,
            'inline' => true,
        ], 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serverPayload(Server $server): array
    {
        $meta = $server->metadata ?? [];

        if (isset($meta['ssh_deploy']['private_key'])) {
            unset($meta['ssh_deploy']['private_key']);
        }

        return [
            'id' => $server->id,
            'name' => $server->name,
            'hostname' => $server->hostname,
            'ip_address' => $server->ip_address,
            'status' => $server->status?->value ?? (string) $server->status,
            'last_seen_at' => $server->last_seen_at?->toIso8601String(),
            'metadata' => $meta,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashAnalyzerMetrics(Server $server, Carbon $since): array
    {
        return CrashAnalyzerMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderByDesc('sampled_at')
            ->limit(8000)
            ->get(['collector', 'sampled_at', 'payload'])
            ->sortBy('sampled_at')
            ->values()
            ->map(fn (CrashAnalyzerMetric $m) => [
                'collector' => $m->collector,
                'sampled_at' => $m->sampled_at?->toIso8601String(),
                'payload' => $m->payload,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashAnalyzerEvents(Server $server, Carbon $since): array
    {
        return CrashAnalyzerEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '>=', $since)
            ->orderByDesc('detected_at')
            ->limit(500)
            ->get()
            ->map(fn (CrashAnalyzerEvent $e) => [
                'event_type' => $e->event_type,
                'severity' => $e->severity,
                'title' => $e->title,
                'details' => $e->details,
                'detected_at' => $e->detected_at?->toIso8601String(),
                'payload' => $e->payload,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashAnalyzerReports(Server $server, Carbon $since): array
    {
        return CrashAnalyzerReport::query()
            ->where('server_id', $server->id)
            ->where('generated_at', '>=', $since)
            ->latest('generated_at')
            ->limit(50)
            ->get()
            ->map(fn (CrashAnalyzerReport $r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'trigger_type' => $r->trigger_type,
                'generated_at' => $r->generated_at?->toIso8601String(),
                'report_json' => $r->report_json,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashHunterMetrics(Server $server, Carbon $since): array
    {
        return CrashHunterMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderByDesc('sampled_at')
            ->limit(8000)
            ->get(['collector', 'sampled_at', 'payload'])
            ->sortBy('sampled_at')
            ->values()
            ->map(fn (CrashHunterMetric $m) => [
                'collector' => $m->collector,
                'sampled_at' => $m->sampled_at?->toIso8601String(),
                'payload' => $m->payload,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashHunterEvents(Server $server, Carbon $since): array
    {
        return CrashHunterEvent::query()
            ->where('server_id', $server->id)
            ->where('detected_at', '>=', $since)
            ->orderByDesc('detected_at')
            ->limit(500)
            ->get()
            ->map(fn (CrashHunterEvent $e) => [
                'event_type' => $e->event_type,
                'severity' => $e->severity,
                'title' => $e->title,
                'details' => $e->details,
                'detected_at' => $e->detected_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashHunterIncidents(Server $server, Carbon $since): array
    {
        return CrashHunterIncident::query()
            ->where('server_id', $server->id)
            ->where('started_at', '>=', $since)
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (CrashHunterIncident $i) => [
                'external_id' => $i->external_id,
                'triggers' => $i->triggers,
                'snapshot_count' => $i->snapshot_count,
                'started_at' => $i->started_at?->toIso8601String(),
                'ended_at' => $i->ended_at?->toIso8601String(),
                'summary' => $i->summary,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashHunterReports(Server $server, Carbon $since): array
    {
        return CrashHunterReport::query()
            ->where('server_id', $server->id)
            ->where('generated_at', '>=', $since)
            ->latest('generated_at')
            ->limit(50)
            ->get()
            ->map(fn (CrashHunterReport $r) => [
                'id' => $r->id,
                'external_id' => $r->external_id,
                'trigger_type' => $r->trigger_type,
                'generated_at' => $r->generated_at?->toIso8601String(),
                'report_json' => $r->report_json,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crashHunterSnapshots(Server $server, Carbon $since): array
    {
        return CrashHunterSnapshot::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->latest('sampled_at')
            ->limit(100)
            ->get(['slot', 'sampled_at', 'payload'])
            ->map(fn (CrashHunterSnapshot $s) => [
                'slot' => $s->slot,
                'sampled_at' => $s->sampled_at?->toIso8601String(),
                'payload' => $s->payload,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestWitness(Server $server): ?array
    {
        $witness = CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->latest('received_at')
            ->first();

        if ($witness === null) {
            return null;
        }

        return [
            'status' => $witness->status,
            'received_at' => $witness->received_at?->toIso8601String(),
            'payload' => $witness->payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeMetricRows($handle, string $source, ?string $sampledAt, string $collector, array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                fputcsv($handle, [$source, $sampledAt, $collector, (string) $key, $value === null ? '' : (string) $value]);

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (is_scalar($subValue) || $subValue === null) {
                        fputcsv($handle, [
                            $source,
                            $sampledAt,
                            $collector,
                            $key.'.'.$subKey,
                            $subValue === null ? '' : (string) $subValue,
                        ]);
                    }
                }
            }
        }
    }

    private function filename(Server $server, Carbon $since, string $extension): string
    {
        return sprintf(
            'doctor-suite-%d-%sh-%s.%s',
            $server->id,
            (int) max(1, $since->diffInHours(now())),
            now()->format('Y-m-d_His'),
            $extension,
        );
    }
}
