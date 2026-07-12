<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Enums\ServerStatus;
use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Models\StatusPageSetting;
use App\Support\UserTimezone;

final class StatusPageService
{
    public function __construct(
        private readonly MonitorRunnerService $monitors,
    ) {}

    public function isEnabled(): bool
    {
        return StatusPageSetting::current()->is_enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $settings = StatusPageSetting::current();
        $servers = $this->visibleServers($settings);
        $monitors = $this->visibleMonitors($settings);
        $incidents = $this->recentIncidents();

        $serversOnline = collect($servers)->where('online', true)->count();
        $monitorsUp = collect($monitors)->where('status', 'up')->count();
        $openIncidents = MonitoringIncident::query()->where('status', 'open')->count();

        $global = 'operational';

        if ($openIncidents > 0 || collect($monitors)->contains('status', 'down')) {
            $global = 'partial_outage';
        }

        if (collect($servers)->where('online', false)->count() > count($servers) / 2) {
            $global = 'major_outage';
        }

        return [
            'title' => $settings->title,
            'noindex' => $settings->noindex,
            'global_status' => $global,
            'global_label' => match ($global) {
                'major_outage' => 'Major outage',
                'partial_outage' => 'Partial outage',
                default => 'All systems operational',
            },
            'updated_at' => UserTimezone::now()->format('d/m/Y H:i:s'),
            'servers' => $servers,
            'monitors' => $monitors,
            'incidents' => $incidents,
            'counts' => [
                'servers_online' => $serversOnline,
                'servers_total' => count($servers),
                'monitors_up' => $monitorsUp,
                'monitors_total' => count($monitors),
                'open_incidents' => $openIncidents,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleServers(StatusPageSetting $settings): array
    {
        $query = Server::query()->where('is_master', false)->orderBy('name');
        $ids = $settings->visible_server_ids;

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(fn (Server $server) => [
            'name' => $server->name,
            'status' => $server->status->value,
            'online' => in_array($server->status, [ServerStatus::Online, ServerStatus::Degraded], true),
            'last_seen' => UserTimezone::format($server->last_seen_at, 'd/m/Y H:i'),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleMonitors(StatusPageSetting $settings): array
    {
        $query = Monitor::query()->where('is_active', true)->orderBy('name');
        $ids = $settings->visible_monitor_ids;

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function (Monitor $monitor) {
            $stats = $this->monitors->statsForMonitor($monitor, 30);

            return [
                'name' => $monitor->name,
                'type' => $monitor->type->label(),
                'status' => $monitor->last_status ?? 'unknown',
                'response_ms' => $monitor->last_response_ms,
                'uptime_30d' => $stats['uptime_percent'],
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentIncidents(): array
    {
        return MonitoringIncident::query()
            ->orderByDesc('went_down_at')
            ->limit(15)
            ->get()
            ->map(fn (MonitoringIncident $i) => [
                'resource' => $i->resource_name,
                'trigger' => $i->trigger,
                'message' => $i->message,
                'status' => $i->status,
                'went_down_at' => UserTimezone::format($i->went_down_at, 'd/m/Y H:i'),
                'recovered_at' => UserTimezone::format($i->recovered_at, 'd/m/Y H:i'),
            ])
            ->all();
    }
}
