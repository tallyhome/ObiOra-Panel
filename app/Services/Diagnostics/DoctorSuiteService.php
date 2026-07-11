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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serverOverview(Server $server, int $historyMinutes = 60): array
    {
        $report = $server->latestDiagnosticReport;
        $crashDashboard = $this->crashMetrics->dashboardData($server, $historyMinutes);
        $hunterDashboard = $this->crashHunterMetrics->dashboardData($server, $historyMinutes);

        return [
            'doctor' => $this->formatDoctorReport($report),
            'crash_analyzer' => [
                'summary' => $crashDashboard['summary'] ?? [],
                'events' => array_slice($crashDashboard['events'] ?? [], 0, 20),
                'reports' => $crashDashboard['reports'] ?? [],
                'charts' => $crashDashboard['charts'] ?? [],
                'journal_boot' => $this->latestMetricPayload($server, 'journal_boot'),
                'hardware' => $this->latestMetricPayload($server, 'hardware'),
                'tools' => $this->latestMetricPayload($server, 'tools'),
            ],
            'crash_hunter' => $hunterDashboard,
        ];
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return list<array<string, mixed>>
     */
    public function fleetOverview(Collection $servers): array
    {
        return $servers->map(function (Server $server) {
            $report = $server->latestDiagnosticReport;
            $lastMetric = CrashAnalyzerMetric::query()
                ->where('server_id', $server->id)
                ->latest('sampled_at')
                ->first();
            $criticalEvents = CrashAnalyzerEvent::query()
                ->where('server_id', $server->id)
                ->where('severity', 'critical')
                ->where('detected_at', '>=', now()->subDay())
                ->count();
            $reportsCount = CrashAnalyzerReport::query()
                ->where('server_id', $server->id)
                ->count();
            $hunterLast = CrashHunterMetric::query()
                ->where('server_id', $server->id)
                ->latest('sampled_at')
                ->first();
            $hunterIncidents = CrashHunterIncident::query()
                ->where('server_id', $server->id)
                ->count();
            $hunterWitness = CrashHunterWitness::query()
                ->where('server_id', $server->id)
                ->latest('received_at')
                ->first();

            $agents = $this->agentStatus->flags($server);

            return [
                'id' => $server->id,
                'name' => $server->name,
                'hostname' => $server->hostname,
                'doctor_score' => $report?->score,
                'doctor_status' => $report?->status,
                'doctor_last_at' => $report?->generated_at?->toIso8601String(),
                'crash_last_metric_at' => $lastMetric?->sampled_at?->toIso8601String(),
                'crash_critical_24h' => $criticalEvents,
                'crash_reports' => $reportsCount,
                'hunter_last_metric_at' => $hunterLast?->sampled_at?->toIso8601String(),
                'hunter_incidents' => $hunterIncidents,
                'hunter_witness_status' => $hunterWitness?->status ?? (($server->metadata ?? [])['crash_hunter']['witness_status'] ?? null),
                'deployed' => $agents['any'],
                'agents_slave' => $agents['slave'],
                'agents_doctor' => $agents['doctor'],
                'agents_crash' => $agents['crash'],
                'agents_crash_hunter' => $agents['crash_hunter'],
                'deploy_remote_host' => ($server->metadata ?? [])['doctor_deploy']['remote_host'] ?? null,
                'display_ip' => ($server->metadata ?? [])['doctor_deploy']['remote_host'] ?? $server->ip_address,
            ];
        })->values()->all();
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
            'modules' => $modules,
            'signature_verified' => $report->signature_verified,
        ];
    }
}
