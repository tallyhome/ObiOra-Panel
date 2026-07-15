<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\AlertPolicy;
use App\Models\AlertPolicyState;
use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\Server;

final class AlertPolicyEvaluator
{
    public function __construct(
        private readonly AlertMetricReader $metrics,
        private readonly AlertNotificationDispatcher $notifier,
        private readonly MaintenanceWindowService $maintenance,
    ) {}

    /**
     * @return array{evaluated: int, opened: int, resolved: int, notified: int}
     */
    public function evaluateAll(): array
    {
        $stats = ['evaluated' => 0, 'opened' => 0, 'resolved' => 0, 'notified' => 0];

        AlertPolicy::query()
            ->where('is_enabled', true)
            ->orderBy('id')
            ->each(function (AlertPolicy $policy) use (&$stats): void {
                foreach ($this->targetsForPolicy($policy) as $target) {
                    $stats['evaluated']++;
                    $result = $this->evaluateTarget($policy, $target['type'], $target['id']);
                    $stats['opened'] += $result['opened'];
                    $stats['resolved'] += $result['resolved'];
                    $stats['notified'] += $result['notified'];
                }
            });

        return $stats;
    }

    /**
     * @return array{opened: int, resolved: int, notified: int}
     */
    public function evaluateTarget(AlertPolicy $policy, string $resourceType, int $resourceId): array
    {
        if ($this->maintenance->isSilenced($resourceType, $resourceId)) {
            return ['opened' => 0, 'resolved' => 0, 'notified' => 0];
        }

        $read = $this->metrics->read($policy, $resourceType, $resourceId);
        $matches = $this->metrics->matches($policy, $read['value']);

        $state = AlertPolicyState::query()->firstOrCreate([
            'alert_policy_id' => $policy->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        if (! $matches) {
            return $this->resolveIfNeeded($policy, $resourceType, $resourceId, $state);
        }

        if ($state->condition_met_since === null) {
            $state->forceFill(['condition_met_since' => now()])->save();
        }

        $metMinutes = $state->condition_met_since?->diffInMinutes(now()) ?? 0;

        if ($metMinutes < $policy->duration_minutes) {
            return ['opened' => 0, 'resolved' => 0, 'notified' => 0];
        }

        $incident = MonitoringIncident::query()
            ->where('alert_policy_id', $policy->id)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('status', 'open')
            ->first();

        $opened = 0;
        $notified = 0;

        if ($incident === null) {
            $incident = MonitoringIncident::query()->create([
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'resource_name' => $read['resource_name'],
                'trigger' => $policy->name,
                'message' => $read['message'],
                'alert_policy_id' => $policy->id,
                'went_down_at' => now(),
                'status' => 'open',
            ]);
            $opened = 1;
            $notified = $this->notifier->notify($incident, $policy);
            $state->forceFill(['last_notified_at' => now()])->save();

            return ['opened' => $opened, 'resolved' => 0, 'notified' => $notified];
        }

        if ($policy->repeat_minutes > 0 && $this->shouldRepeat($incident, $policy)) {
            $notified = $this->notifier->notify($incident, $policy);
            $state->forceFill(['last_notified_at' => now()])->save();
        }

        return ['opened' => 0, 'resolved' => 0, 'notified' => $notified];
    }

    /**
     * @return array{opened: int, resolved: int, notified: int}
     */
    private function resolveIfNeeded(
        AlertPolicy $policy,
        string $resourceType,
        int $resourceId,
        AlertPolicyState $state,
    ): array {
        $state->forceFill([
            'condition_met_since' => null,
            'last_notified_at' => null,
        ])->save();

        $incident = MonitoringIncident::query()
            ->where('alert_policy_id', $policy->id)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('status', 'open')
            ->first();

        if ($incident === null) {
            return ['opened' => 0, 'resolved' => 0, 'notified' => 0];
        }

        $incident->forceFill([
            'status' => 'resolved',
            'recovered_at' => now(),
        ])->save();

        return ['opened' => 0, 'resolved' => 1, 'notified' => 0];
    }

    private function shouldRepeat(MonitoringIncident $incident, AlertPolicy $policy): bool
    {
        $last = $incident->last_notified_at ?? $incident->went_down_at;

        return $last->diffInMinutes(now()) >= $policy->repeat_minutes;
    }

    /**
     * @return list<array{type: string, id: int}>
     */
    private function targetsForPolicy(AlertPolicy $policy): array
    {
        $applyTo = $policy->apply_to;
        $ids = $policy->apply_target_ids ?? [];

        if (in_array($policy->metric, ['monitor_status', 'ssl_expiry_days'], true)) {
            $applyTo = 'monitors';
        }

        if (in_array($policy->metric, [
            'cpu_usage_percent', 'cpu_steal_percent', 'memory_usage_percent',
            'disk_usage_percent', 'load_per_core', 'uptime_seconds', 'agent_no_data_minutes',
        ], true)) {
            $applyTo = $applyTo === 'monitors' ? 'monitors' : 'servers';
        }

        return match ($applyTo) {
            'servers' => Server::query()
                ->where('is_master', false)
                ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
                ->pluck('id')
                ->map(fn ($id) => ['type' => 'server', 'id' => (int) $id])
                ->all(),
            'monitors' => Monitor::query()
                ->where('is_active', true)
                ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
                ->pluck('id')
                ->map(fn ($id) => ['type' => 'monitor', 'id' => (int) $id])
                ->all(),
            default => array_merge(
                Server::query()->where('is_master', false)->pluck('id')
                    ->map(fn ($id) => ['type' => 'server', 'id' => (int) $id])->all(),
                Monitor::query()->where('is_active', true)->pluck('id')
                    ->map(fn ($id) => ['type' => 'monitor', 'id' => (int) $id])->all(),
            ),
        };
    }
}
