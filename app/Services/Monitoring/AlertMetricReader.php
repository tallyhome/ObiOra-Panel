<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Enums\AlertPolicyOperator;
use App\Models\AlertPolicy;
use App\Models\Monitor;
use App\Models\Server;
use App\Models\ServerMetricSample;

final class AlertMetricReader
{
    /**
     * @return array{value: ?float, message: string, resource_name: string}
     */
    public function read(AlertPolicy $policy, string $resourceType, int $resourceId): array
    {
        if ($resourceType === 'server') {
            return $this->readServer($policy, $resourceId);
        }

        return $this->readMonitor($policy, $resourceId);
    }

    /**
     * @return array{value: ?float, message: string, resource_name: string}
     */
    private function readServer(AlertPolicy $policy, int $serverId): array
    {
        $server = Server::query()->find($serverId);

        if ($server === null) {
            return ['value' => null, 'message' => 'Serveur introuvable', 'resource_name' => '#'.$serverId];
        }

        $sample = ServerMetricSample::query()
            ->where('server_id', $server->id)
            ->orderByDesc('sampled_at')
            ->first();

        $name = $server->name;

        if ($policy->metric === 'agent_no_data_minutes') {
            $lastAt = $sample?->sampled_at ?? $server->last_seen_at;
            $minutes = $lastAt ? $lastAt->diffInMinutes(now()) : 9999;

            return [
                'value' => (float) $minutes,
                'message' => "Aucune donnée agent depuis {$minutes} min (seuil: {$policy->value})",
                'resource_name' => $name,
            ];
        }

        if ($sample === null) {
            return ['value' => null, 'message' => 'Aucun échantillon métrique', 'resource_name' => $name];
        }

        $value = match ($policy->metric) {
            'cpu_usage_percent' => $sample->cpu_percent,
            'cpu_steal_percent' => $sample->cpu_steal_percent,
            'memory_usage_percent' => $sample->memory_percent,
            'disk_usage_percent' => $sample->disk_percent,
            'load_per_core' => $this->loadPerCore($sample),
            'uptime_seconds' => $sample->uptime_seconds !== null ? (float) $sample->uptime_seconds : null,
            default => null,
        };

        $display = $value !== null ? (string) round($value, 2) : 'N/A';

        return [
            'value' => $value !== null ? (float) $value : null,
            'message' => "{$policy->metricLabel()} = {$display} (seuil: {$policy->value}{$policy->value_unit})",
            'resource_name' => $name,
        ];
    }

    /**
     * @return array{value: ?float, message: string, resource_name: string}
     */
    private function readMonitor(AlertPolicy $policy, int $monitorId): array
    {
        $monitor = Monitor::query()->find($monitorId);

        if ($monitor === null) {
            return ['value' => null, 'message' => 'Moniteur introuvable', 'resource_name' => '#'.$monitorId];
        }

        $name = $monitor->name;

        if ($policy->metric === 'monitor_status') {
            $down = $monitor->last_status === 'down' ? 0.0 : 1.0;

            return [
                'value' => $down,
                'message' => $down === 0.0
                    ? "Moniteur {$name} est DOWN"
                    : "Moniteur {$name} est UP",
                'resource_name' => $name,
            ];
        }

        if ($policy->metric === 'ssl_expiry_days') {
            $days = $this->sslDaysFromMonitor($monitor);

            return [
                'value' => $days,
                'message' => $days !== null
                    ? "SSL expire dans {$days} jour(s) pour {$monitor->displayTarget()}"
                    : 'SSL non disponible',
                'resource_name' => $name,
            ];
        }

        return ['value' => null, 'message' => 'Métrique non applicable', 'resource_name' => $name];
    }

    private function loadPerCore(ServerMetricSample $sample): ?float
    {
        if ($sample->load_1 === null) {
            return null;
        }

        $cores = (int) (($sample->payload ?? [])['cpu_cores'] ?? 0);

        if ($cores < 1) {
            $cores = 1;
        }

        return round((float) $sample->load_1 / $cores, 2);
    }

    private function sslDaysFromMonitor(Monitor $monitor): ?float
    {
        $check = $monitor->checks()->orderByDesc('checked_at')->first();
        $days = ($check->metrics ?? [])['ssl_days_remaining'] ?? null;

        return is_numeric($days) ? (float) $days : null;
    }

    public function matches(AlertPolicy $policy, ?float $value): bool
    {
        if ($value === null) {
            return false;
        }

        $threshold = (float) $policy->value;
        $op = $policy->operator instanceof AlertPolicyOperator
            ? $policy->operator
            : AlertPolicyOperator::tryFrom((string) $policy->operator) ?? AlertPolicyOperator::Gt;

        return match ($op) {
            AlertPolicyOperator::Gt => $value > $threshold,
            AlertPolicyOperator::Lt => $value < $threshold,
            AlertPolicyOperator::Gte => $value >= $threshold,
            AlertPolicyOperator::Lte => $value <= $threshold,
            AlertPolicyOperator::Eq => abs($value - $threshold) < 0.001,
        };
    }
}
