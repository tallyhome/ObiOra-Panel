<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringAlert;
use App\Models\MonitoringIncident;
use App\Models\NotificationLog;
use App\Support\UserTimezone;

final class MonitoringIncidentService
{
    public function __construct(
        private readonly ServerUnifiedProfileService $profiles,
    ) {}
    /**
     * @return list<array<string, mixed>>
     */
    public function openIncidents(int $limit = 20): array
    {
        $incidents = MonitoringIncident::query()
            ->where('status', 'open')
            ->orderByDesc('went_down_at')
            ->limit($limit)
            ->get()
            ->map(fn (MonitoringIncident $i) => $this->serializeIncident($i))
            ->all();

        if ($incidents !== []) {
            return $incidents;
        }

        return $this->legacyOpenAlerts($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allIncidents(int $limit = 50, ?string $status = null, ?string $resourceType = null): array
    {
        $query = MonitoringIncident::query()->orderByDesc('went_down_at')->limit($limit);

        if ($status === 'open') {
            $query->where('status', 'open');
        } elseif ($status === 'resolved') {
            $query->where('status', 'resolved');
        }

        if ($resourceType === 'servers') {
            $query->where('resource_type', 'server');
        } elseif ($resourceType === 'monitors') {
            $query->where('resource_type', 'monitor');
        }

        $rows = $query->get()->map(fn (MonitoringIncident $i) => $this->serializeIncident($i))->all();

        if ($rows !== []) {
            return $rows;
        }

        return $this->legacyAllAlerts($limit, $status, $resourceType);
    }

    public function openCount(): int
    {
        $count = MonitoringIncident::query()->where('status', 'open')->count();

        if ($count > 0) {
            return $count;
        }

        return MonitoringAlert::query()->whereNull('read_at')->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notificationLogs(int $limit = 50): array
    {
        return NotificationLog::query()
            ->with(['contact:id,name', 'incident:id,trigger,resource_name'])
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'status' => $log->status,
                'contact' => $log->contact?->name ?? '—',
                'incident' => $log->incident
                    ? $log->incident->trigger.' — '.$log->incident->resource_name
                    : '—',
                'sent_at' => UserTimezone::format($log->sent_at, 'd/m/Y H:i:s'),
                'response' => $log->response ? substr($log->response, 0, 120) : null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIncident(MonitoringIncident $incident): array
    {
        $isOpen = $incident->status === 'open';
        $wentDown = $incident->went_down_at;

        return [
            'id' => $incident->id,
            'resource' => $incident->resource_name,
            'resource_type' => $incident->resource_type,
            'resource_id' => $incident->resource_id,
            'trigger' => $incident->trigger,
            'message' => $incident->message,
            'action_links' => $this->profiles->incidentActionLinks(
                $incident->resource_type,
                (int) $incident->resource_id,
                $incident->trigger,
            ),
            'went_down_at' => UserTimezone::format($wentDown, 'd/m/Y H:i:s'),
            'went_down_at_iso' => $wentDown?->toIso8601String(),
            'recovered_at' => $isOpen ? null : UserTimezone::format($incident->recovered_at, 'd/m/Y H:i:s'),
            'duration' => $isOpen
                ? ($wentDown?->diffForHumans(short: true) ?? '—')
                : ($wentDown && $incident->recovered_at
                    ? $wentDown->diffForHumans($incident->recovered_at, true)
                    : '—'),
            'status' => $isOpen ? 'open' : 'resolved',
            'severity' => 'warning',
            'type' => $incident->resource_type,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legacyOpenAlerts(int $limit): array
    {
        return MonitoringAlert::query()
            ->with('server:id,name')
            ->whereNull('read_at')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (MonitoringAlert $alert) => $this->serializeLegacyAlert($alert))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legacyAllAlerts(int $limit, ?string $status, ?string $resourceType): array
    {
        $query = MonitoringAlert::query()->with('server:id,name')->latest()->limit($limit);

        if ($status === 'open') {
            $query->whereNull('read_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('read_at');
        }

        if ($resourceType === 'servers') {
            $query->whereNotNull('server_id');
        }

        return $query->get()
            ->map(fn (MonitoringAlert $alert) => $this->serializeLegacyAlert($alert))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLegacyAlert(MonitoringAlert $alert): array
    {
        $isOpen = $alert->read_at === null;
        $resourceType = $alert->server_id !== null ? 'server' : 'monitor';
        $trigger = $this->triggerLabel($alert->type);

        return [
            'id' => $alert->id,
            'resource' => $alert->server?->name ?? 'Panel',
            'resource_type' => $resourceType,
            'resource_id' => $alert->server_id,
            'trigger' => $trigger,
            'message' => $alert->message ?: $alert->title,
            'action_links' => $alert->server_id !== null
                ? $this->profiles->incidentActionLinks($resourceType, (int) $alert->server_id, $trigger)
                : [],
            'went_down_at' => UserTimezone::format($alert->created_at, 'd/m/Y H:i:s'),
            'went_down_at_iso' => $alert->created_at?->toIso8601String(),
            'recovered_at' => $isOpen ? null : UserTimezone::format($alert->read_at, 'd/m/Y H:i:s'),
            'duration' => $isOpen
                ? $alert->created_at?->diffForHumans(short: true)
                : ($alert->read_at && $alert->created_at
                    ? $alert->created_at->diffForHumans($alert->read_at, true)
                    : '—'),
            'status' => $isOpen ? 'open' : 'resolved',
            'severity' => $alert->severity,
            'type' => $alert->type,
            'legacy' => true,
        ];
    }

    private function triggerLabel(string $type): string
    {
        return match ($type) {
            'server_offline' => 'Serveur hors ligne',
            'diagnostic_critical' => 'Doctor critique',
            'ssl_expiry' => 'SSL expirant',
            'crash_analyzer' => 'Crash Analyzer',
            'signature_invalid' => 'Signature invalide',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
