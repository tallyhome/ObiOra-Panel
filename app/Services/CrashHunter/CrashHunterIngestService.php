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

final class CrashHunterIngestService
{
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
        $last = CrashHunterWitness::query()
            ->where('server_id', $server->id)
            ->latest('received_at')
            ->first();

        $ageSeconds = $last?->received_at ? $receivedAt->diffInSeconds($last->received_at) : 0;
        $timeout = (int) config('crash_hunter.witness_timeout_seconds', 15);
        $death = (int) config('crash_hunter.witness_death_seconds', 30);

        $status = 'alive';
        if ($ageSeconds > $death) {
            $status = 'dead';
        } elseif ($ageSeconds > $timeout) {
            $status = 'timeout';
        }

        $record = CrashHunterWitness::query()->create([
            'server_id' => $server->id,
            'status' => $status,
            'received_at' => $receivedAt,
            'payload' => $payload,
        ]);

        $this->touchServer($server, $receivedAt, array_merge($payload, ['witness_status' => $status]));

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestIncident(Server $server, array $payload): CrashHunterIncident
    {
        $externalId = (string) ($payload['incident_id'] ?? $payload['external_id'] ?? uniqid('inc_', true));

        return CrashHunterIncident::query()->updateOrCreate(
            ['server_id' => $server->id, 'external_id' => $externalId],
            [
                'triggers' => $payload['triggers'] ?? [],
                'snapshot_count' => (int) ($payload['snapshot_count'] ?? 0),
                'started_at' => $this->parseTime($payload['started_at'] ?? null),
                'ended_at' => $this->parseTime($payload['ended_at'] ?? null),
                'summary' => is_array($payload['summary'] ?? null) ? $payload['summary'] : ['raw' => $payload],
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

        $record = CrashHunterReport::query()->create([
            'server_id' => $server->id,
            'external_id' => (string) ($reportJson['report_id'] ?? $payload['report_id'] ?? null),
            'hostname' => (string) ($reportJson['hostname'] ?? $payload['hostname'] ?? $server->hostname),
            'trigger_type' => (string) ($reportJson['reboot_detection']['reason'] ?? $payload['trigger_type'] ?? 'unknown'),
            'generated_at' => $generatedAt,
            'report_json' => $reportJson,
            'bundle_path' => $payload['bundle_path'] ?? null,
        ]);

        $this->touchServer($server, $generatedAt, $payload);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function ingestEvent(Server $server, array $event): CrashHunterEvent
    {
        return CrashHunterEvent::query()->create([
            'server_id' => $server->id,
            'event_type' => (string) ($event['event_type'] ?? $event['event'] ?? 'unknown'),
            'severity' => (string) ($event['severity'] ?? 'warning'),
            'title' => (string) ($event['title'] ?? $event['event'] ?? 'Événement'),
            'details' => (string) ($event['details'] ?? $event['detail'] ?? ''),
            'payload' => is_array($event['payload'] ?? null) ? $event['payload'] : $event,
            'detected_at' => $this->parseTime($event['detected_at'] ?? $event['timestamp_us'] ?? null),
            'notified' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function touchServer(Server $server, Carbon $sampledAt, array $payload): void
    {
        $server->forceFill([
            'last_seen_at' => now(),
            'status' => 'online',
            'metadata' => array_merge($server->metadata ?? [], [
                'crash_hunter' => [
                    'last_metrics_at' => $sampledAt->toIso8601String(),
                    'hostname' => $payload['hostname'] ?? $server->hostname,
                    'incident_mode' => (bool) ($payload['incident_mode'] ?? false),
                    'witness_status' => $payload['witness_status'] ?? 'alive',
                    'ring_count' => $payload['ring_count'] ?? null,
                    'version' => $payload['crashhunter_version'] ?? null,
                ],
            ]),
        ])->save();
    }

    private function parseTime(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return now();
        }
    }
}
