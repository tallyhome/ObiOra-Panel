<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\Monitoring\MonitoringAlertService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class DiagnosticReportManager
{
    public function __construct(
        private readonly ReportSignatureVerifier $verifier,
        private readonly MonitoringAlertService $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Server $server, array $payload): DiagnosticReport
    {
        $signatureVerified = $this->verifier->verify($payload, $server);
        $requireSignature = (bool) config('obiora.diagnostics.require_signature', false);

        if ($requireSignature && ! $signatureVerified) {
            $this->alerts->recordInvalidSignature($server);
            throw ValidationException::withMessages([
                'signature' => ['Signature HMAC invalide ou cle manquante.'],
            ]);
        }

        $critical = $this->extractCriticalFindings($payload);
        $status = $this->resolveStatus((int) ($payload['score'] ?? 0), $critical);

        $report = DiagnosticReport::query()->create([
            'server_id' => $server->id,
            'external_id' => isset($payload['id'])
                ? (string) $payload['id']
                : (isset($payload['report_id']) ? (string) $payload['report_id'] : null),
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
            'support_mode' => (bool) ($payload['support_mode'] ?? $payload['host']['support_mode'] ?? false),
            'signature' => is_array($payload['signature'] ?? null)
                ? (string) ($payload['signature']['value'] ?? '')
                : null,
            'signature_verified' => $signatureVerified,
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
                    'signature_verified' => $signatureVerified,
                ],
            ]),
        ])->save();

        if ($critical !== []) {
            $this->alerts->recordCriticalReport($server, $report);
        }

        $this->processSslFindings($server, $payload);

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
     * @param  array<string, mixed>  $payload
     */
    private function processSslFindings(Server $server, array $payload): void
    {
        foreach ($payload['results'] ?? [] as $result) {
            if (($result['module'] ?? '') !== 'ssl') {
                continue;
            }
            foreach ($result['findings'] ?? [] as $finding) {
                $level = $finding['level'] ?? '';
                if (! in_array($level, ['WARNING', 'CRITICAL'], true)) {
                    continue;
                }
                $details = (string) ($finding['details'] ?? '');
                if (preg_match('/host=([^,\s]+).*jours restants:\s*(-?\d+)/i', $details, $matches)) {
                    $this->alerts->recordSslExpiry(
                        $server,
                        $matches[1],
                        (int) $matches[2],
                        (string) ($finding['title'] ?? ''),
                    );
                }
            }
        }
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
