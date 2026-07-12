<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashAnalyzerReport;
use App\Models\CrashHunterIncident;
use App\Models\CrashHunterMetric;
use App\Models\CrashHunterReport;
use App\Models\CrashHunterWitness;
use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerMetricsService;
use App\Services\CrashHunter\CrashHunterMetricsService;
use App\Support\ServerAgentStatus;
use Illuminate\Support\Collection;

/**
 * Agrège les résultats Doctor + Crash Analyzer pour la page Doctor & Suite.
 */
final class DoctorSuiteService
{
    public function __construct(
        private readonly CrashAnalyzerMetricsService $crashMetrics,
        private readonly CrashHunterMetricsService $crashHunterMetrics,
        private readonly ServerAgentStatus $agentStatus,
        private readonly DoctorSuitePlainLanguage $plainLanguage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serverOverview(Server $server, int $historyMinutes = 30, bool $lite = true): array
    {
        $report = $server->latestDiagnosticReport;
        $crashDashboard = $lite
            ? $this->crashMetrics->doctorSummary($server, $historyMinutes)
            : $this->crashMetrics->dashboardData($server, $historyMinutes);
        $hunterDashboard = $lite
            ? $this->crashHunterMetrics->doctorDashboard($server, $historyMinutes)
            : $this->crashHunterMetrics->dashboardData($server, $historyMinutes);
        $doctor = $this->formatDoctorReport($report);
        $latestPayloads = $this->latestMetricPayloads($server, ['journal_boot', 'hardware', 'tools']);

        return [
            'doctor' => $doctor,
            'crash_analyzer' => [
                'summary' => $crashDashboard['summary'] ?? [],
                'events' => array_slice($crashDashboard['events'] ?? [], 0, 20),
                'reports' => $crashDashboard['reports'] ?? [],
                'charts' => $lite ? [] : ($crashDashboard['charts'] ?? []),
                'journal_boot' => $latestPayloads['journal_boot'] ?? null,
                'hardware' => $latestPayloads['hardware'] ?? null,
                'tools' => $latestPayloads['tools'] ?? null,
            ],
            'crash_hunter' => $hunterDashboard,
            'plain_summary' => $this->plainLanguage->summarize([
                'doctor' => $doctor,
                'crash_analyzer' => [
                    'summary' => $crashDashboard['summary'] ?? [],
                    'events' => array_slice($crashDashboard['events'] ?? [], 0, 20),
                ],
                'crash_hunter' => $hunterDashboard,
            ]),
        ];
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return list<array<string, mixed>>
     */
    public function fleetOverview(Collection $servers): array
    {
        if ($servers->isEmpty()) {
            return [];
        }

        $ids = $servers->pluck('id')->all();
        $agentFlags = $this->agentStatus->flagsBatch($servers);

        $crashLastMetrics = CrashAnalyzerMetric::query()
            ->whereIn('server_id', $ids)
            ->selectRaw('server_id, MAX(sampled_at) as last_at')
            ->groupBy('server_id')
            ->get()
            ->keyBy('server_id');

        $critical24h = CrashAnalyzerEvent::query()
            ->whereIn('server_id', $ids)
            ->where('severity', 'critical')
            ->where('detected_at', '>=', now()->subDay())
            ->selectRaw('server_id, COUNT(*) as total')
            ->groupBy('server_id')
            ->pluck('total', 'server_id');

        $reportCounts = CrashAnalyzerReport::query()
            ->whereIn('server_id', $ids)
            ->selectRaw('server_id, COUNT(*) as total')
            ->groupBy('server_id')
            ->pluck('total', 'server_id');

        $hunterLastMetrics = CrashHunterMetric::query()
            ->whereIn('server_id', $ids)
            ->selectRaw('server_id, MAX(sampled_at) as last_at')
            ->groupBy('server_id')
            ->get()
            ->keyBy('server_id');

        $hunterIncidentCounts = CrashHunterIncident::query()
            ->whereIn('server_id', $ids)
            ->selectRaw('server_id, COUNT(*) as total')
            ->groupBy('server_id')
            ->pluck('total', 'server_id');

        $hunterWitnesses = CrashHunterWitness::query()
            ->whereIn('server_id', $ids)
            ->orderByDesc('received_at')
            ->get()
            ->unique('server_id')
            ->keyBy('server_id');

        return $servers->map(function (Server $server) use (
            $agentFlags,
            $crashLastMetrics,
            $critical24h,
            $reportCounts,
            $hunterLastMetrics,
            $hunterIncidentCounts,
            $hunterWitnesses,
        ) {
            $report = $server->latestDiagnosticReport;
            $agents = $agentFlags[$server->id] ?? $this->agentStatus->flags($server);
            $lastMetric = $crashLastMetrics->get($server->id);
            $hunterLast = $hunterLastMetrics->get($server->id);
            $hunterWitness = $hunterWitnesses->get($server->id);
            $meta = $server->metadata ?? [];

            return [
                'id' => $server->id,
                'name' => $server->name,
                'hostname' => $server->hostname,
                'doctor_score' => $report?->score,
                'doctor_status' => $report?->status,
                'doctor_last_at' => $report?->generated_at?->toIso8601String(),
                'crash_last_metric_at' => $lastMetric?->last_at,
                'crash_critical_24h' => (int) ($critical24h[$server->id] ?? 0),
                'crash_reports' => (int) ($reportCounts[$server->id] ?? 0),
                'hunter_last_metric_at' => $hunterLast?->last_at,
                'hunter_incidents' => (int) ($hunterIncidentCounts[$server->id] ?? 0),
                'hunter_witness_status' => $hunterWitness?->status ?? ($meta['crash_hunter']['witness_status'] ?? null),
                'deployed' => $agents['any'],
                'agents_slave' => $agents['slave'],
                'agents_doctor' => $agents['doctor'],
                'agents_crash' => $agents['crash'],
                'agents_crash_hunter' => $agents['crash_hunter'],
                'deploy_remote_host' => $meta['doctor_deploy']['remote_host'] ?? null,
                'display_ip' => $meta['doctor_deploy']['remote_host'] ?? $server->ip_address,
            ];
        })->values()->all();
    }

    /**
     * @param  list<string>  $collectors
     * @return array<string, array<string, mixed>|null>
     */
    private function latestMetricPayloads(Server $server, array $collectors): array
    {
        $result = [];

        foreach ($collectors as $collector) {
            $payload = CrashAnalyzerMetric::query()
                ->where('server_id', $server->id)
                ->where('collector', $collector)
                ->latest('sampled_at')
                ->value('payload');

            $result[$collector] = is_array($payload) ? $payload : null;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestMetricPayload(Server $server, string $collector): ?array
    {
        $metric = CrashAnalyzerMetric::query()
            ->where('server_id', $server->id)
            ->where('collector', $collector)
            ->latest('sampled_at')
            ->first();

        return $metric?->payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatDoctorReport(?DiagnosticReport $report): ?array
    {
        if ($report === null) {
            return null;
        }

        $json = $report->report_json ?? [];
        $modules = [];
        foreach ($json['results'] ?? [] as $result) {
            $modules[] = [
                'module' => $result['module'] ?? 'unknown',
                'status' => $result['status'] ?? 'ok',
                'findings' => $result['findings'] ?? [],
            ];
        }

        return [
            'id' => $report->id,
            'score' => $report->score,
            'status' => $report->status,
            'doctor_version' => $report->doctor_version,
            'generated_at' => $report->generated_at?->toIso8601String(),
            'critical_findings' => $report->critical_findings ?? [],
            'action_findings' => $this->plainLanguage->extractActionFindings($json),
            'modules' => $modules,
            'signature_verified' => $report->signature_verified,
        ];
    }
}
