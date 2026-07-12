<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerMetricSample;
use Illuminate\Support\Carbon;

final class ServerMetricsHealthService
{
    /**
     * @return array{evaluated: int, degraded: int, offline: int}
     */
    public function evaluateAll(): array
    {
        $onlineSeconds = (int) config('obiora.monitoring.agent_online_seconds', 180);
        $offlineSeconds = (int) config('obiora.monitoring.agent_offline_seconds', 900);

        $stats = ['evaluated' => 0, 'degraded' => 0, 'offline' => 0];

        Server::query()->each(function (Server $server) use ($onlineSeconds, $offlineSeconds, &$stats): void {
            if ($server->is_master) {
                return;
            }

            $stats['evaluated']++;
            $lastAt = $this->resolveLastMetricAt($server);

            if ($lastAt === null) {
                if ($server->last_seen_at === null) {
                    return;
                }

                $lastAt = $server->last_seen_at;
            }

            $age = $lastAt->diffInSeconds(now());
            $newStatus = match (true) {
                $age <= $onlineSeconds => ServerStatus::Online,
                $age <= $offlineSeconds => ServerStatus::Degraded,
                default => ServerStatus::Offline,
            };

            if ($server->status !== $newStatus) {
                $server->update(['status' => $newStatus]);
            }

            if ($newStatus === ServerStatus::Degraded) {
                $stats['degraded']++;
            } elseif ($newStatus === ServerStatus::Offline) {
                $stats['offline']++;
            }
        });

        return $stats;
    }

    private function resolveLastMetricAt(Server $server): ?Carbon
    {
        $fromMeta = ($server->metadata ?? [])['monitor_metrics']['last_at'] ?? null;

        if (is_string($fromMeta) && $fromMeta !== '') {
            return Carbon::parse($fromMeta);
        }

        $latest = ServerMetricSample::query()
            ->where('server_id', $server->id)
            ->orderByDesc('sampled_at')
            ->value('sampled_at');

        return $latest instanceof Carbon ? $latest : null;
    }
}
