<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\DiagnosticReport;
use App\Models\Server;
use Illuminate\Support\Carbon;

final class DiagnosticReportManager
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Server $server, array $payload): DiagnosticReport
    {
        $critical = $this->extractCriticalFindings($payload);
        $status = $this->resolveStatus((int) ($payload['score'] ?? 0), $critical);

        $report = DiagnosticReport::query()->create([
            'server_id' => $server->id,
            'external_id' => $payload['generated_at'] ?? null,
            'schema_version' => (string) ($payload['host']['schema_version'] ?? '1.0'),
            'doctor_version' => (string) ($payload['version'] ?? ''),
            'score' => (int) ($payload['score'] ?? 0),
            'status' => $status,
            'hostname' => (string) ($payload['host']['hostname'] ?? $server->hostname),
            'generated_at' => isset($payload['generated_at'])
                ? Carbon::parse((string) $payload['generated_at'])
                : now(),
            'report_json' => $payload,
            'critical_findings' => $critical,
            'signature' => is_array($payload['signature'] ?? null)
                ? (string) ($payload['signature']['value'] ?? '')
                : null,
        ]);

        $server->forceFill([
            'last_seen_at' => now(),
            'status' => 'online',
            'metadata' => array_merge($server->metadata ?? [], [
                'doctor' => [
                    'score' => $report->score,
                    'status' => $report->status,
                    'last_report_at' => $report->generated_at?->toIso8601String(),
                    'critical_count' => count($critical),
                ],
            ]),
        ])->save();

        return $report;
    }

    public function latest(Server $server): ?DiagnosticReport
    {
        return $server->diagnosticReports()->latest('generated_at')->first();
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function heartbeat(Server $server, array $metrics): void
    {
        $server->forceFill([
            'last_seen_at' => now(),
            'status' => ($metrics['online'] ?? true) ? 'online' : 'offline',
            'metadata' => array_merge($server->metadata ?? [], [
                'doctor_heartbeat' => array_merge($metrics, ['at' => now()->toIso8601String()]),
            ]),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractCriticalFindings(array $payload): array
    {
        $critical = [];
        foreach ($payload['results'] ?? [] as $result) {
            foreach ($result['findings'] ?? [] as $finding) {
                if (($finding['level'] ?? '') === 'CRITICAL') {
                    $critical[] = [
                        'module' => $result['module'] ?? 'unknown',
                        'title' => $finding['title'] ?? '',
                        'details' => $finding['details'] ?? '',
                    ];
                }
            }
        }

        return $critical;
    }

    /**
     * @param  list<array<string, mixed>>  $critical
     */
    private function resolveStatus(int $score, array $critical): string
    {
        if ($critical !== []) {
            return 'critical';
        }
        if ($score < 70) {
            return 'warning';
        }

        return 'ok';
    }
}
