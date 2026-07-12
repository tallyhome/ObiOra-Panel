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
use App\Services\Diagnostics\DiagnosticsAgentVersionService;
use Illuminate\Support\Carbon;

final class CrashHunterIngestService
{
    public function __construct(
        private readonly DiagnosticsAgentVersionService $agentVersions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestMetrics(Server $server, array $payload): int
    {
        $sampledAt = $this->parseTime($payload['sampled_at'] ?? $payload['timestamp_us'] ?? null);
        $inserted = 0;

        foreach ($payload['metrics'] ?? [] as $collector => $data) {
            CrashHunterMetric::query()->create([
                'server_id' => $server->id,
                'collector' => (string) $collector,
                'sampled_at' => $sampledAt,
                'payload' => is_array($data) ? $data : ['value' => $data],
            ]);
            $inserted++;
        }

        foreach ($payload['events'] ?? [] as $event) {
            if (is_array($event)) {
                $this->ingestEvent($server, $event);
            }
        }

        $this->touchServer($server, $sampledAt, $payload);

        return $inserted;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestSnapshots(Server $server, array $payload): int
    {
        $inserted = 0;
        foreach ($payload['snapshots'] ?? [] as $snap) {
            if (! is_array($snap)) {
                continue;
            }
            CrashHunterSnapshot::query()->create([
                'server_id' => $server->id,
                'slot' => isset($snap['slot']) ? (int) $snap['slot'] : null,
                'sampled_at' => $this->parseTime($snap['timestamp_us'] ?? $snap['sampled_at'] ?? null),
                'payload' => $snap,
            ]);
            $inserted++;
        }

        if ($inserted > 0) {
            $this->touchServer($server, now(), $payload);
        }

        return $inserted;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestWitness(Server $server, array $payload): CrashHunterWitness
    {
        $receivedAt = now();
        $agentAt = $this->parseTime($payload['timestamp'] ?? $payload['timestamp_us'] ?? null);

        $last = CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->latest('received_at')
            ->first();

        $timeout = (int) config('crash_hunter.witness_timeout_seconds', 15);
        $death = (int) config('crash_hunter.witness_death_seconds', 30);

        $status = 'alive';
        if ($last !== null) {
            $lastAgentAt = $this->parseTime(
                $last->payload['timestamp'] ?? $last->payload['timestamp_us'] ?? $last->received_at,
            );
            $gapSeconds = abs($agentAt->diffInSeconds($lastAgentAt));
            if ($gapSeconds > $death) {
                $status = 'dead';
            } elseif ($gapSeconds > $timeout) {
                $status = 'timeout';
            }
        }

        $record = CrashHunterWitness::query()->create([
            'server_id' => $server->id,
            'status' => $status,
            'received_at' => $receivedAt,
            'payload' => array_merge($payload, [
                'agent_timestamp' => $agentAt->toIso8601String(),
                'gap_seconds' => $last !== null
                    ? abs($agentAt->diffInSeconds($this->parseTime(
                        $last->payload['timestamp'] ?? $last->payload['timestamp_us'] ?? $last->received_at,
                    )))
                    : 0,
            ]),
        ]);

        $this->touchServer($server, $receivedAt, array_merge($payload, [
            'witness_status' => $status,
            'witness_gap_seconds' => $record->payload['gap_seconds'] ?? 0,
        ]));

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestIncident(Server $server, array $payload): CrashHunterIncident
    {
        $externalId = (string) ($payload['incident_id'] ?? $payload['external_id'] ?? uniqid('inc_', true));
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : $payload;

        return CrashHunterIncident::query()->updateOrCreate(
            ['server_id' => $server->id, 'external_id' => $externalId],
            [
                'triggers' => $payload['triggers'] ?? $summary['triggers'] ?? [],
                'snapshot_count' => (int) ($payload['snapshot_count'] ?? $summary['snapshot_count'] ?? 0),
                'started_at' => $this->parseTime($payload['started_at'] ?? $summary['started_at'] ?? null),
                'ended_at' => $this->parseTime($payload['ended_at'] ?? $summary['ended_at'] ?? null),
                'summary' => array_merge($summary, [
                    'status' => $payload['status'] ?? $summary['status'] ?? null,
                ]),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestReport(Server $server, array $payload): CrashHunterReport
    {
        $reportJson = is_array($payload['report_json'] ?? null)
            ? $payload['report_json']
            : (is_array($payload) ? $payload : []);

        $generatedAt = $this->parseTime(
            $reportJson['generated_at'] ?? $payload['generated_at'] ?? null,
        );

        $triggerType = (string) (
            $payload['trigger_type']
            ?? ($reportJson['reboot_detection']['reason'] ?? null)
            ?? 'unknown'
        );

        $record = CrashHunterReport::query()->updateOrCreate(
            [
                'server_id' => $server->id,
                'external_id' => (string) ($reportJson['report_id'] ?? $payload['report_id'] ?? uniqid('rpt_', true)),
            ],
            [
                'hostname' => (string) ($reportJson['hostname'] ?? $payload['hostname'] ?? $server->hostname),
                'trigger_type' => $triggerType,
                'generated_at' => $generatedAt,
                'report_json' => $reportJson,
                'bundle_path' => $payload['bundle_path'] ?? null,
            ],
        );

        $this->touchServer($server, $generatedAt, array_merge($payload, [
            'last_report_id' => $record->external_id,
            'last_report_verdict' => $reportJson['diagnosis']['verdict'] ?? null,
        ]));

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestEvents(Server $server, array $payload): int
    {
        $inserted = 0;
        foreach ($payload['events'] ?? [] as $event) {
            if (is_array($event)) {
                $this->ingestEvent($server, $event);
                $inserted++;
            }
        }

        if ($inserted > 0) {
            $this->touchServer($server, now(), $payload);
        }

        return $inserted;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function ingestEvent(Server $server, array $event): CrashHunterEvent
    {
        $eventType = (string) ($event['event_type'] ?? $event['event'] ?? 'unknown');
        $detectedAt = $this->parseTime($event['detected_at'] ?? $event['timestamp_us'] ?? $event['timestamp'] ?? null);

        $existing = CrashHunterEvent::query()
            ->where('server_id', $server->id)
            ->where('event_type', $eventType)
            ->where('detected_at', $detectedAt)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return CrashHunterEvent::query()->create([
            'server_id' => $server->id,
            'event_type' => $eventType,
            'severity' => (string) ($event['severity'] ?? 'warning'),
            'title' => (string) ($event['title'] ?? $event['event'] ?? 'Événement'),
            'details' => (string) ($event['details'] ?? $event['detail'] ?? ''),
            'payload' => is_array($event['payload'] ?? null) ? $event['payload'] : $event,
            'detected_at' => $detectedAt,
            'notified' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function touchServer(Server $server, Carbon $sampledAt, array $payload): void
    {
        $existingMeta = ($server->metadata ?? [])['crash_hunter'] ?? [];
        $version = $this->resolveCrashHunterVersion($server, $payload, $existingMeta);

        $server->forceFill([
            'last_seen_at' => now(),
            'status' => 'online',
            'metadata' => array_merge($server->metadata ?? [], [
                'crash_hunter' => array_merge($existingMeta, [
                    'last_metrics_at' => $sampledAt->toIso8601String(),
                    'hostname' => $payload['hostname'] ?? $existingMeta['hostname'] ?? $server->hostname,
                    'incident_mode' => (bool) ($payload['incident_mode'] ?? $existingMeta['incident_mode'] ?? false),
                    'witness_status' => $payload['witness_status'] ?? $existingMeta['witness_status'] ?? 'alive',
                    'witness_gap_seconds' => $payload['witness_gap_seconds'] ?? $existingMeta['witness_gap_seconds'] ?? null,
                    'ring_count' => $payload['ring_count'] ?? $existingMeta['ring_count'] ?? null,
                    'version' => $version,
                    'sequence_id' => $payload['sequence_id'] ?? $existingMeta['sequence_id'] ?? null,
                    'last_report_id' => $payload['last_report_id'] ?? $existingMeta['last_report_id'] ?? null,
                    'last_report_verdict' => $payload['last_report_verdict'] ?? $existingMeta['last_report_verdict'] ?? null,
                ]),
            ]),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existingMeta
     */
    private function resolveCrashHunterVersion(Server $server, array $payload, array $existingMeta): ?string
    {
        $existing = $this->normalizeVersion($existingMeta['version'] ?? null);
        $payloadVersion = $this->normalizeVersion($payload['crashhunter_version'] ?? null);

        if ($payloadVersion !== null) {
            if ($existing !== null && version_compare($payloadVersion, $existing, '<')) {
                return $existing;
            }

            return $payloadVersion;
        }

        if ($existing !== null) {
            return $existing;
        }

        if (($payload['metrics'] ?? []) === [] && ($payload['witness_status'] ?? null) === null) {
            return null;
        }

        $meta = $server->metadata ?? [];
        $components = is_array($meta['doctor_deploy']['components'] ?? null)
            ? $meta['doctor_deploy']['components']
            : [];

        if (
            in_array('crash_hunter', $components, true)
            || in_array('doctor_suite', $components, true)
            || isset($meta['crash_hunter']['last_metrics_at'])
            || CrashHunterMetric::query()->where('server_id', $server->id)->exists()
        ) {
            $bundled = $this->agentVersions->bundledVersions()['crash_hunter'] ?? null;

            return is_string($bundled) && $bundled !== '' ? $bundled : null;
        }

        return null;
    }

    private function normalizeVersion(mixed $version): ?string
    {
        if (! is_string($version) || $version === '') {
            return null;
        }

        return $version;
    }

    private function parseTime(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 1_000_000_000_000) {
                return Carbon::createFromTimestampMs((int) $numeric);
            }

            return Carbon::createFromTimestamp((int) $numeric);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return now();
        }
    }
}
