<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Enums\ServerStatus;
use App\Models\Monitor;
use App\Models\Server;
use App\Support\UserTimezone;

final class MonitoringDashboardService
{
    public function __construct(
        private readonly MonitoringFleetService $fleet,
        private readonly MonitoringIncidentService $incidents,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function summary(): array
    {
        $servers = Server::query()->get();
        $online = $servers->filter(fn (Server $s) => in_array($s->status, [ServerStatus::Online, ServerStatus::Degraded], true))->count();
        $degraded = $servers->filter(fn (Server $s) => $s->status === ServerStatus::Degraded)->count();
        $total = $servers->count();

        $monitors = Monitor::query()->get();
        $monitorsUp = $monitors->where('last_status', 'up')->count();
        $monitorsDown = $monitors->where('last_status', 'down')->count();
        $monitorsTotal = $monitors->count();

        return [
            'servers_total' => $total,
            'servers_online' => $online,
            'servers_degraded' => $degraded,
            'servers_offline' => max(0, $total - $online),
            'monitors_total' => $monitorsTotal,
            'monitors_up' => $monitorsUp,
            'monitors_down' => $monitorsDown,
            'monitors_unknown' => max(0, $monitorsTotal - $monitorsUp - $monitorsDown),
            'open_incidents' => $this->incidents->openCount(),
            'plan_label' => 'ObiOra self-hosted',
            'timezone' => UserTimezone::resolve(),
            'timezone_label' => UserTimezone::label(),
            'now' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentServers(int $limit = 5): array
    {
        return collect($this->fleet->fleetSnapshot())
            ->take($limit)
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'online' => ($row['status'] ?? '') === 'online' && ($row['ping_success'] ?? false),
                'last_seen' => $this->formatLastSeen($row['id']),
                'ip' => $row['ip'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentMonitors(int $limit = 5): array
    {
        return Monitor::query()
            ->orderByDesc('last_checked_at')
            ->limit($limit)
            ->get()
            ->map(fn (Monitor $monitor) => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'type' => $monitor->type->value,
                'type_label' => $monitor->type->label(),
                'target' => $monitor->displayTarget(),
                'status' => $monitor->last_status ?? 'unknown',
                'response_ms' => $monitor->last_response_ms,
                'last_checked' => UserTimezone::format($monitor->last_checked_at, 'd/m/Y H:i:s'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openIncidents(int $limit = 10): array
    {
        return $this->incidents->openIncidents($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serverRows(): array
    {
        return Server::query()
            ->with('latestDiagnosticReport')
            ->orderByDesc('is_master')
            ->orderBy('name')
            ->get()
            ->map(fn (Server $server) => $this->serializeServerRow($server))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeServerRow(Server $server): array
    {
        $report = $server->latestDiagnosticReport;
        $smart = $this->diskHealthFromReport($report?->report_json ?? []);

        return [
            'id' => $server->id,
            'name' => $server->name,
            'is_master' => $server->is_master,
            'status' => $server->status->value,
            'online' => in_array($server->status, [ServerStatus::Online, ServerStatus::Degraded], true),
            'os_label' => trim(($server->os_name ?? '').' '.($server->os_version ?? '')),
            'kernel' => ($server->metadata ?? [])['kernel'] ?? null,
            'disk_health' => $smart,
            'last_seen' => UserTimezone::format($server->last_seen_at, 'd/m/Y H:i:s'),
            'last_seen_human' => $server->last_seen_at?->diffForHumans(),
            'agent_token_masked' => $this->maskToken($server->agent_token),
            'agent_token' => $server->agent_token,
            'ip_address' => $server->ip_address,
            'tags' => $server->tags ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $reportJson
     */
    private function diskHealthFromReport(array $reportJson): string
    {
        foreach ($reportJson['results'] ?? [] as $result) {
            if (($result['module'] ?? '') !== 'smart') {
                continue;
            }
            $status = $result['status'] ?? 'ok';

            return match ($status) {
                'critical', 'error' => 'Failed',
                'warning' => 'Warning',
                default => 'Passed',
            };
        }

        return 'N/A';
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) < 12) {
            return '••••••••';
        }

        return substr($token, 0, 6).'…'.substr($token, -6);
    }

    private function formatLastSeen(int $serverId): string
    {
        $server = Server::query()->find($serverId);

        return UserTimezone::format($server?->last_seen_at, 'd/m/Y H:i:s');
    }
}
