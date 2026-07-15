<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\CrashAnalyzerEvent;
use App\Models\CrashHunterIncident;
use App\Models\DiagnosticReport;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Support\DedicatedHostProfileRegistry;
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

        $openIncidentRows = MonitoringIncident::query()
            ->where('resource_type', 'server')
            ->where('resource_id', $server->id)
            ->where('status', 'open')
            ->orderByDesc('went_down_at')
            ->limit(8)
            ->get()
            ->map(fn (MonitoringIncident $incident) => [
                'trigger' => $incident->trigger,
                'message' => $incident->message,
                'went_down_at' => UserTimezone::format($incident->went_down_at, 'd/m/Y H:i'),
                'action_links' => $this->incidentActionLinks(
                    $incident->resource_type,
                    (int) $incident->resource_id,
                    $incident->trigger,
                ),
            ])
            ->all();

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
                'type' => $server->type->value,
                'host_profile' => DedicatedHostProfileRegistry::labelFor($server),
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
                'open_incident_rows' => $openIncidentRows,
            ],
            'correlations' => $this->correlationHints($server, $report, $lastCrash, $openIncidentRows),
            'links' => $this->actionLinks($server),
        ];
    }

    /**
     * @return list<array{label: string, route: string, icon?: string}>
     */
    public function actionLinks(Server $server): array
    {
        $links = [
            ['label' => 'Métriques', 'route' => route('monitoring.servers.metrics', $server)],
            ['label' => 'Doctor', 'route' => route('doctor.index', ['server' => $server->id])],
            ['label' => 'Crash Analyzer', 'route' => route('crash-analyzer.index', ['server' => $server->id])],
            ['label' => 'Flotte avancée', 'route' => route('monitoring.fleet')],
        ];

        foreach (DedicatedHostProfileRegistry::panelLinks($server) as $extra) {
            $links[] = $extra;
        }

        return $links;
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

            if (str_contains(strtolower($trigger), 'reboot') || str_contains(strtolower($trigger), 'crash') || str_contains(strtolower($trigger), 'redémarrage')) {
                $links[] = [
                    'label' => 'Crash Analyzer',
                    'route' => route('crash-analyzer.index', ['server' => $server->id]),
                ];
            }

            if (str_contains(strtolower($trigger), 'freeze') || str_contains(strtolower($trigger), 'stall')) {
                $links[] = [
                    'label' => 'CrashHunter',
                    'route' => route('doctor.index', ['server' => $server->id, 'tab' => 'crashhunter']),
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
     * @param  list<array<string, mixed>>  $openIncidents
     * @return list<array{label: string, detail: string, route?: string}>
     */
    private function correlationHints(Server $server, ?DiagnosticReport $report, ?CrashAnalyzerEvent $lastCrash, array $openIncidents = []): array
    {
        $hints = [];

        foreach ($openIncidents as $incident) {
            $trigger = strtolower((string) ($incident['trigger'] ?? ''));

            if (str_contains($trigger, 'disk')) {
                $hints[] = [
                    'label' => 'Disque saturé',
                    'detail' => ($incident['message'] ?? '').' — Doctor module disk + backups',
                    'route' => route('doctor.index', ['server' => $server->id]),
                ];
            }

            if (str_contains($trigger, 'reboot') || str_contains($trigger, 'redémarrage') || str_contains($trigger, 'crash')) {
                $hints[] = [
                    'label' => 'Reboot / crash',
                    'detail' => $incident['message'] ?? 'Événement reboot — journal Crash Analyzer',
                    'route' => route('crash-analyzer.index', ['server' => $server->id]),
                ];
            }

            if (str_contains($trigger, 'freeze') || str_contains($trigger, 'stall')) {
                $hints[] = [
                    'label' => 'Freeze / stall',
                    'detail' => $incident['message'] ?? 'Incident CrashHunter — snapshots freeze',
                    'route' => route('doctor.index', ['server' => $server->id, 'tab' => 'crashhunter']),
                ];
            }
        }

        if (in_array($server->status->value, ['offline', 'unreachable'], true)) {
            if ($lastCrash !== null) {
                $hints[] = [
                    'label' => 'Crash récent',
                    'detail' => $lastCrash->title.' — '.UserTimezone::format($lastCrash->detected_at, 'd/m/Y H:i'),
                    'route' => route('crash-analyzer.index', ['server' => $server->id]),
                ];
            }

            if ($report !== null && $report->score !== null && $report->score < 70) {
                $hints[] = [
                    'label' => 'Doctor dégradé',
                    'detail' => 'Score '.$report->score.'% — vérifier les findings critiques',
                    'route' => route('doctor.index', ['server' => $server->id]),
                ];
            }

            if ($hints === []) {
                $hints[] = [
                    'label' => 'Serveur hors ligne',
                    'detail' => 'Vérifier ping ICMP, agent métriques et derniers événements crash',
                    'route' => route('monitoring.fleet'),
                ];
            }
        }

        $agentMeta = ($server->metadata ?? [])['monitor_agent'] ?? [];
        if (($agentMeta['last_push_at'] ?? null) === null && $server->last_seen_at !== null) {
            $hints[] = [
                'label' => 'Agent métriques',
                'detail' => 'Aucun push récent — vérifier obiora-agent.service',
                'route' => route('monitoring.servers.metrics', $server),
            ];
        }

        return $hints;
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
