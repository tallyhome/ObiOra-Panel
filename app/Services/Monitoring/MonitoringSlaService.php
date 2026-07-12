<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Monitor;
use App\Models\MonitoringIncident;
use App\Models\Server;
use App\Models\ServerPingSample;
use App\Support\UserTimezone;
use Illuminate\Support\Carbon;

final class MonitoringSlaService
{
    public function __construct(
        private readonly MonitorRunnerService $monitors,
    ) {}

    public function serverUptimePercent(Server $server, int $days = 30): float
    {
        $since = now()->subDays($days);

        $samples = ServerPingSample::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->get();

        if ($samples->isEmpty()) {
            return in_array($server->status->value, ['online', 'degraded'], true) ? 100.0 : 0.0;
        }

        $ok = $samples->where('success', true)->count();

        return round(($ok / $samples->count()) * 100, 2);
    }

    public function monitorUptimePercent(Monitor $monitor, int $days = 30): float
    {
        $since = now()->subDays($days);

        return $this->monitors->statsForPeriod($monitor, $since, now())['uptime_percent'];
    }

    /**
     * @return array<string, mixed>
     */
    public function serverReport(Server $server, int $days = 30): array
    {
        $since = now()->subDays($days);
        $uptime30 = $this->serverUptimePercent($server, 30);
        $uptime60 = $this->serverUptimePercent($server, 60);
        $uptime90 = $this->serverUptimePercent($server, 90);

        $incidents = MonitoringIncident::query()
            ->where('resource_type', 'server')
            ->where('resource_id', $server->id)
            ->where('went_down_at', '>=', $since)
            ->orderByDesc('went_down_at')
            ->limit(50)
            ->get()
            ->map(fn (MonitoringIncident $i) => [
                'trigger' => $i->trigger,
                'message' => $i->message,
                'status' => $i->status,
                'went_down_at' => UserTimezone::format($i->went_down_at, 'd/m/Y H:i'),
                'recovered_at' => UserTimezone::format($i->recovered_at, 'd/m/Y H:i'),
            ])
            ->all();

        $monitors = Monitor::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Monitor $m) => [
                'name' => $m->name,
                'uptime_30d' => $this->monitorUptimePercent($m, 30),
                'status' => $m->last_status ?? 'unknown',
            ])
            ->all();

        return [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status->value,
                'ip' => $server->ip_address,
            ],
            'period_days' => $days,
            'generated_at' => UserTimezone::now()->format('d/m/Y H:i:s'),
            'uptime' => [
                '30d' => $uptime30,
                '60d' => $uptime60,
                '90d' => $uptime90,
                'selected' => $this->serverUptimePercent($server, $days),
            ],
            'incidents' => $incidents,
            'monitors' => $monitors,
        ];
    }

    public function renderReportHtml(Server $server, int $days = 30): string
    {
        $data = $this->serverReport($server, $days);

        $rows = '';
        foreach ($data['incidents'] as $incident) {
            $rows .= '<tr>'
                .'<td>'.e($incident['trigger']).'</td>'
                .'<td>'.e($incident['message']).'</td>'
                .'<td>'.e($incident['status']).'</td>'
                .'<td>'.e($incident['went_down_at']).'</td>'
                .'<td>'.e($incident['recovered_at'] ?? '—').'</td>'
                .'</tr>';
        }

        $monitorRows = '';
        foreach ($data['monitors'] as $monitor) {
            $monitorRows .= '<tr>'
                .'<td>'.e($monitor['name']).'</td>'
                .'<td>'.e((string) $monitor['uptime_30d']).'%</td>'
                .'<td>'.e($monitor['status']).'</td>'
                .'</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rapport SLA — {$data['server']['name']}</title>
<style>
body { font-family: system-ui, sans-serif; margin: 2rem; color: #111; }
h1 { font-size: 1.35rem; }
table { border-collapse: collapse; width: 100%; margin-top: 1rem; font-size: 0.85rem; }
th, td { border: 1px solid #ccc; padding: 0.4rem 0.55rem; text-align: left; }
th { background: #f3f4f6; }
.kpi { display: inline-block; margin-right: 1.5rem; }
</style>
</head>
<body>
<h1>Rapport SLA Monitoring — {$data['server']['name']}</h1>
<p>Généré le {$data['generated_at']} · Période {$days} jours</p>
<div>
  <div class="kpi"><strong>Uptime 30j</strong><br>{$data['uptime']['30d']}%</div>
  <div class="kpi"><strong>Uptime 60j</strong><br>{$data['uptime']['60d']}%</div>
  <div class="kpi"><strong>Uptime 90j</strong><br>{$data['uptime']['90d']}%</div>
</div>
<h2>Incidents serveur</h2>
<table>
<thead><tr><th>Déclencheur</th><th>Message</th><th>Statut</th><th>Début</th><th>Récupéré</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<h2>Moniteurs (uptime 30j)</h2>
<table>
<thead><tr><th>Nom</th><th>Uptime</th><th>Statut</th></tr></thead>
<tbody>{$monitorRows}</tbody>
</table>
</body>
</html>
HTML;
    }
}
