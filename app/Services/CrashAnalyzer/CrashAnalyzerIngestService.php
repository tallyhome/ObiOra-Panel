<?php

declare(strict_types=1);

namespace App\Services\CrashAnalyzer;

use App\Events\CrashAnalyzer\CrashDetected;
use App\Events\CrashAnalyzer\UnexpectedRebootDetected;
use App\Jobs\CrashAnalyzer\ProcessCrashReportJob;
use App\Models\CrashAnalyzerEvent;
use App\Models\CrashAnalyzerMetric;
use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use App\Services\Diagnostics\DiagnosticsAgentVersionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class CrashAnalyzerIngestService
{
    public function __construct(
        private readonly CrashAnalyzerNotificationService $notifications,
        private readonly DiagnosticsAgentVersionService $agentVersions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestMetrics(Server $server, array $payload): int
    {
        $sampledAt = isset($payload['sampled_at'])
            ? Carbon::createFromTimestamp((int) $payload['sampled_at'])
            : now();

        $inserted = 0;
        foreach ($payload['metrics'] ?? [] as $collector => $data) {
            CrashAnalyzerMetric::query()->create([
                'server_id' => $server->id,
                'collector' => (string) $collector,
                'sampled_at' => $sampledAt,
                'payload' => is_array($data) ? $data : ['value' => $data],
            ]);
            $inserted++;
        }

        foreach ($payload['events'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }
            $this->ingestEvent($server, $event);
        }

        $existingMeta = ($server->metadata ?? [])['crash_analyzer'] ?? [];
        $crashMeta = array_merge($existingMeta, [
            'last_metrics_at' => $sampledAt->toIso8601String(),
            'hostname' => $payload['hostname'] ?? $server->hostname,
        ]);

        if (isset($payload['crash_analyzer_version']) && (string) $payload['crash_analyzer_version'] !== '') {
            $crashMeta['version'] = (string) $payload['crash_analyzer_version'];
        } elseif (! $this->hasStoredVersion($crashMeta) && $this->shouldBackfillBundledVersion($server, $payload)) {
            $bundled = $this->agentVersions->bundledVersions()['crash_analyzer'] ?? null;
            if (is_string($bundled) && $bundled !== '') {
                $crashMeta['version'] = $bundled;
            }
        }

        $server->forceFill([
            'last_seen_at' => now(),
            'status' => 'online',
            'metadata' => array_merge($server->metadata ?? [], [
                'crash_analyzer' => $crashMeta,
            ]),
        ])->save();

        return $inserted;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function ingestEvent(Server $server, array $event): CrashAnalyzerEvent
    {
        $detectedAt = isset($event['detected_at'])
            ? Carbon::createFromTimestamp((int) $event['detected_at'])
            : now();

        $eventType = (string) ($event['event_type'] ?? 'unknown');
        $title = (string) ($event['title'] ?? 'Événement');

        $existing = CrashAnalyzerEvent::query()
            ->where('server_id', $server->id)
            ->where('event_type', $eventType)
            ->where('title', $title)
            ->where('detected_at', '>=', $detectedAt->copy()->subMinutes(5))
            ->exists();

        if ($existing) {
            return CrashAnalyzerEvent::query()
                ->where('server_id', $server->id)
                ->where('event_type', $eventType)
                ->where('title', $title)
                ->latest('detected_at')
                ->firstOrFail();
        }

        $record = CrashAnalyzerEvent::query()->create([
            'server_id' => $server->id,
            'event_type' => (string) ($event['event_type'] ?? 'unknown'),
            'severity' => (string) ($event['severity'] ?? 'warning'),
            'title' => (string) ($event['title'] ?? 'Événement'),
            'details' => (string) ($event['details'] ?? ''),
            'payload' => is_array($event['payload'] ?? null) ? $event['payload'] : [],
            'detected_at' => $detectedAt,
            'notified' => false,
        ]);

        if ($record->event_type === 'unexpected_reboot') {
            event(new UnexpectedRebootDetected($server, $record));
        } elseif ($record->severity === 'critical') {
            event(new CrashDetected($server, $record));
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestReport(Server $server, array $payload): CrashAnalyzerReport
    {
        $pdfPath = null;
        if (! empty($payload['pdf_base64'])) {
            $pdfPath = $this->storePdf($server, (string) $payload['report_id'], (string) $payload['pdf_base64']);
        }

        $trigger = is_array($payload['trigger_event'] ?? null) ? $payload['trigger_event'] : [];

        $report = CrashAnalyzerReport::query()->create([
            'server_id' => $server->id,
            'external_id' => (string) ($payload['report_id'] ?? null),
            'hostname' => (string) ($payload['hostname'] ?? $server->hostname),
            'trigger_type' => (string) ($trigger['event_type'] ?? null),
            'generated_at' => isset($payload['generated_at'])
                ? Carbon::parse((string) $payload['generated_at'])
                : now(),
            'report_json' => is_array($payload['report_json'] ?? null) ? $payload['report_json'] : $payload,
            'pdf_path' => $pdfPath,
        ]);

        ProcessCrashReportJob::dispatch($server->id, $report->id);

        return $report;
    }

    private function storePdf(Server $server, string $reportId, string $base64): string
    {
        $path = "crash-analyzer/{$server->id}/{$reportId}.pdf";
        Storage::disk('local')->put($path, base64_decode($base64, true) ?: '');

        return $path;
    }

    /**
     * @param  array<string, mixed>  $crashMeta
     */
    private function hasStoredVersion(array $crashMeta): bool
    {
        $version = $crashMeta['version'] ?? null;

        return is_string($version) && $version !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldBackfillBundledVersion(Server $server, array $payload): bool
    {
        if (($payload['metrics'] ?? []) === []) {
            return false;
        }

        $meta = $server->metadata ?? [];
        $components = is_array($meta['doctor_deploy']['components'] ?? null)
            ? $meta['doctor_deploy']['components']
            : [];

        if (in_array('crash_analyzer', $components, true) || in_array('doctor_suite', $components, true)) {
            return true;
        }

        if (isset($meta['crash_analyzer']['last_metrics_at'])) {
            return true;
        }

        return CrashAnalyzerMetric::query()->where('server_id', $server->id)->exists();
    }
}
