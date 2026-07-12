<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashHunterIncident;
use App\Models\DiagnosticReport;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Support\UserTimezone;

final class ServerUnifiedProfileService
{
    /**
     * @return array<string, mixed>
     */
    public function profile(Server $server): array
    {
        $report = $server->latestDiagnosticReport;
        $openIncidents = MonitoringIncident::query()
            ->where('resource_type', 'server')
            ->where('resource_id', $server->id)
            ->where('status', 'open')
            ->count();

        $lastCrash = CrashAnalyzerEvent::query()
            ->where('server_id', $server->id)
            ->orderByDesc('detected_at')
            ->first();

        $crashHunterOpen = CrashHunterIncident::query()
            ->where('server_id', $server->id)
            ->whereNull('ended_at')
            ->count();

        return [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
                'ip' => $server->ip_address,
                'os_label' => trim(($server->os_name ?? '').' '.($server->os_version ?? '')),
                'last_seen' => UserTimezone::format($server->last_seen_at, 'd/m/Y H:i:s'),
                'is_master' => $server->is_master,
            ],
            'doctor' => $this->doctorSummary($report),
            'crash' => [
                'last_event' => $lastCrash ? [
                    'type' => $lastCrash->event_type,
                    'severity' => $lastCrash->severity,
                    'at' => UserTimezone::format($lastCrash->detected_at, 'd/m/Y H:i:s'),
                    'message' => $lastCrash->title,
                ] : null,
            ],
            'crash_hunter' => [
                'open_incidents' => $crashHunterOpen,
            ],
            'monitoring' => [
                'open_incidents' => $openIncidents,
            ],
            'links' => $this->actionLinks($server),
        ];
    }

    /**
     * @return list<array{label: string, route: string, icon?: string}>
     */
    public function actionLinks(Server $server): array
    {
        return [
            ['label' => 'Métriques', 'route' => route('monitoring.servers.metrics', $server)],
            ['label' => 'Doctor', 'route' => route('doctor.index', ['server' => $server->id])],
            ['label' => 'Crash Analyzer', 'route' => route('crash-analyzer.index', ['server' => $server->id])],
            ['label' => 'Flotte avancée', 'route' => route('monitoring.fleet')],
        ];
    }

    /**
     * @return list<array{label: string, route: string}>
     */
    public function incidentActionLinks(string $resourceType, int $resourceId, string $trigger = ''): array
    {
        if ($resourceType === 'server') {
            $server = Server::query()->find($resourceId);

            if ($server === null) {
                return [];
            }

            $links = $this->actionLinks($server);

            if (str_contains(strtolower($trigger), 'disk')) {
                array_unshift($links, [
                    'label' => 'Doctor — disque',
                    'route' => route('doctor.index', ['server' => $server->id]),
                ]);
            }

            if (str_contains(strtolower($trigger), 'reboot') || str_contains(strtolower($trigger), 'crash')) {
                $links[] = [
                    'label' => 'Crash Analyzer',
                    'route' => route('crash-analyzer.index', ['server' => $server->id]),
                ];
            }

            return $links;
        }

        if ($resourceType === 'monitor') {
            return [
                ['label' => 'Voir moniteur', 'route' => route('monitoring.monitors.show', $resourceId)],
            ];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function doctorSummary(?DiagnosticReport $report): array
    {
        if ($report === null) {
            return [
                'score' => null,
                'status' => null,
                'generated_at' => null,
                'critical' => 0,
            ];
        }

        $json = $report->report_json ?? [];

        return [
            'score' => $report->score,
            'status' => $report->status,
            'generated_at' => UserTimezone::format($report->generated_at, 'd/m/Y H:i:s'),
            'critical' => count($json['critical_findings'] ?? []),
        ];
    }
}
