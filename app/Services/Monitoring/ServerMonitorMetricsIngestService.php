<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerMetricSample;
use Illuminate\Support\Carbon;

final class ServerMonitorMetricsIngestService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, next_push_seconds: int}
     */
    public function ingest(Server $server, array $payload): array
    {
        $sampledAt = isset($payload['sampled_at'])
            ? Carbon::createFromTimestamp((int) $payload['sampled_at'])
            : now();

        $extras = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];

        if (isset($payload['tcp_connections'])) {
            $extras['tcp_connections'] = (int) $payload['tcp_connections'];
        }

        ServerMetricSample::query()->create([
            'server_id' => $server->id,
            'sampled_at' => $sampledAt,
            'cpu_percent' => $this->decimal($payload['cpu_percent'] ?? null),
            'cpu_steal_percent' => $this->decimal($payload['cpu_steal_percent'] ?? null),
            'memory_percent' => $this->decimal($payload['memory_percent'] ?? null),
            'swap_percent' => $this->decimal($payload['swap_percent'] ?? null),
            'disk_percent' => $this->decimal($payload['disk_percent'] ?? null),
            'load_1' => $this->decimal($payload['load_1'] ?? null),
            'load_5' => $this->decimal($payload['load_5'] ?? null),
            'load_15' => $this->decimal($payload['load_15'] ?? null),
            'uptime_seconds' => isset($payload['uptime_seconds']) ? (int) $payload['uptime_seconds'] : null,
            'payload' => $extras,
        ]);

        $metadata = $server->metadata ?? [];
        $monitorMeta = array_merge($metadata['monitor_metrics'] ?? [], [
            'last_at' => $sampledAt->toIso8601String(),
            'schema_version' => (int) ($payload['schema_version'] ?? 1),
            'agent_version' => $payload['agent_version'] ?? ($monitorMeta['agent_version'] ?? null),
        ]);

        if (! empty($payload['daily_info']) && is_array($payload['daily_info'])) {
            $monitorMeta['daily_info'] = $payload['daily_info'];
            $this->applyDailyInfo($server, $payload['daily_info']);
        }

        $server->forceFill([
            'last_seen_at' => now(),
            'status' => ServerStatus::Online,
            'metadata' => array_merge($metadata, ['monitor_metrics' => $monitorMeta]),
        ])->save();

        return [
            'ok' => true,
            'next_push_seconds' => (int) config('obiora.monitoring.agent_push_interval_seconds', 60),
        ];
    }

    /**
     * @param  array<string, mixed>  $daily
     */
    private function applyDailyInfo(Server $server, array $daily): void
    {
        if (! empty($daily['os_name'])) {
            $server->os_name = (string) $daily['os_name'];
        }

        if (! empty($daily['os_version'])) {
            $server->os_version = (string) $daily['os_version'];
        }

        if (! empty($daily['primary_ip']) && ! $server->is_master) {
            $server->ip_address = (string) $daily['primary_ip'];
        }

        $metadata = $server->metadata ?? [];
        $metadata['kernel'] = $daily['kernel'] ?? ($metadata['kernel'] ?? null);
        $metadata['architecture'] = $daily['architecture'] ?? ($metadata['architecture'] ?? null);
        $server->metadata = $metadata;
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
