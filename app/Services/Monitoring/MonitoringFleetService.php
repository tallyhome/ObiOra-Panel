<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\DiagnosticReport;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Models\ServerPingSample;
use Illuminate\Support\Collection;

final class MonitoringFleetService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fleetSnapshot(): array
    {
        return Server::query()
            ->with('latestDiagnosticReport')
            ->orderBy('name')
            ->get()
            ->map(fn (Server $server) => $this->serializeServer($server))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pingHistory(Server $server, int $hours = 24): array
    {
        return ServerPingSample::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', now()->subHours($hours))
            ->orderBy('sampled_at')
            ->get(['latency_ms', 'success', 'method', 'sampled_at'])
            ->map(fn (ServerPingSample $sample) => [
                'latency_ms' => $sample->latency_ms,
                'success' => $sample->success,
                'method' => $sample->method,
                'at' => $sample->sampled_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scoreHistory(Server $server, int $limit = 30): array
    {
        return DiagnosticReport::query()
            ->where('server_id', $server->id)
            ->latest('generated_at')
            ->limit($limit)
            ->get(['score', 'status', 'generated_at', 'signature_verified'])
            ->reverse()
            ->values()
            ->map(fn (DiagnosticReport $report) => [
                'score' => $report->score,
                'status' => $report->status,
                'signature_verified' => (bool) $report->signature_verified,
                'at' => $report->generated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function compareReports(Server $server, int $leftId, int $rightId): array
    {
        $left = DiagnosticReport::query()->where('server_id', $server->id)->findOrFail($leftId);
        $right = DiagnosticReport::query()->where('server_id', $server->id)->findOrFail($rightId);

        return $this->diffReports($left->report_json ?? [], $right->report_json ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unreadAlerts(int $limit = 20): array
    {
        return MonitoringAlert::query()
            ->with('server:id,name')
            ->whereNull('read_at')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (MonitoringAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'title' => $alert->title,
                'message' => $alert->message,
                'server' => $alert->server?->name,
                'created_at' => $alert->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeServer(Server $server): array
    {
        $ping = $server->metadata['ping'] ?? [];
        $doctor = $server->metadata['doctor'] ?? [];
        $report = $server->latestDiagnosticReport;

        return [
            'id' => $server->id,
            'name' => $server->name,
            'ip' => $server->ip_address,
            'status' => $server->status->value ?? (string) $server->status,
            'ping_ms' => $ping['latency_ms'] ?? null,
            'ping_success' => (bool) ($ping['success'] ?? false),
            'ping_method' => $ping['method'] ?? null,
            'last_seen' => $server->last_seen_at?->diffForHumans(),
            'score' => $report?->score ?? ($doctor['score'] ?? null),
            'doctor_status' => $report?->status ?? ($doctor['status'] ?? null),
            'critical' => count($report?->critical_findings ?? []),
            'signature_verified' => (bool) ($report?->signature_verified ?? false),
            'report_at' => $report?->generated_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function diffReports(array $left, array $right): array
    {
        $leftModules = collect($left['results'] ?? [])->keyBy('module');
        $rightModules = collect($right['results'] ?? [])->keyBy('module');
        $names = $leftModules->keys()->merge($rightModules->keys())->unique()->sort()->values();

        $modules = [];
        $metricDiffs = [];

        foreach ($names as $name) {
            $leftItem = $leftModules->get($name);
            $rightItem = $rightModules->get($name);

            if (! $leftItem || ! $rightItem) {
                $modules[] = [
                    'module' => $name,
                    'change' => $rightItem && ! $leftItem ? 'added' : 'removed',
                ];

                continue;
            }

            $scoreDelta = (int) ($rightItem['score'] ?? 0) - (int) ($leftItem['score'] ?? 0);
            if ($scoreDelta !== 0 || ($leftItem['status'] ?? '') !== ($rightItem['status'] ?? '')) {
                $modules[] = [
                    'module' => $name,
                    'change' => 'modified',
                    'score_delta' => $scoreDelta,
                    'left_score' => $leftItem['score'] ?? null,
                    'right_score' => $rightItem['score'] ?? null,
                ];
            }

            $leftMetrics = $leftItem['metrics'] ?? [];
            $rightMetrics = $rightItem['metrics'] ?? [];
            if (is_array($leftMetrics) && is_array($rightMetrics)) {
                foreach (array_unique(array_merge(array_keys($leftMetrics), array_keys($rightMetrics))) as $metricKey) {
                    $lv = $leftMetrics[$metricKey] ?? null;
                    $rv = $rightMetrics[$metricKey] ?? null;
                    if ($lv !== $rv) {
                        $metricDiffs[] = [
                            'module' => $name,
                            'metric' => $metricKey,
                            'left' => $lv,
                            'right' => $rv,
                        ];
                    }
                }
            }
        }

        return [
            'left_date' => $left['generated_at'] ?? null,
            'right_date' => $right['generated_at'] ?? null,
            'left_score' => $left['score'] ?? null,
            'right_score' => $right['score'] ?? null,
            'score_delta' => (int) ($right['score'] ?? 0) - (int) ($left['score'] ?? 0),
            'modules' => $modules,
            'metrics' => $metricDiffs,
        ];
    }
}
