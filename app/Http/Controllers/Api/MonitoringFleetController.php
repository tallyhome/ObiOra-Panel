<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Services\Monitoring\MonitorRunnerService;
use App\Services\Monitoring\MonitoringDashboardService;
use App\Services\Monitoring\MonitoringFleetService;
use App\Services\Monitoring\ServerMetricsService;
use App\Support\DoctorInstallHelper;
use App\Support\MonitorInstallHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MonitoringFleetController extends Controller
{
    public function summary(MonitoringDashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'summary' => $dashboard->summary(),
            'recent_servers' => $dashboard->recentServers(5),
            'open_incidents' => $dashboard->openIncidents(5),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function fleet(MonitoringFleetService $fleet): JsonResponse
    {
        return response()->json([
            'servers' => $fleet->fleetSnapshot(),
            'alerts' => $fleet->unreadAlerts(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function pingHistory(Server $server, MonitoringFleetService $fleet): JsonResponse
    {
        $hours = (int) config('obiora.diagnostics.ping_history_hours', 24);

        return response()->json([
            'server_id' => $server->id,
            'samples' => $fleet->pingHistory($server, $hours),
        ]);
    }

    public function scoreHistory(Server $server, MonitoringFleetService $fleet): JsonResponse
    {
        return response()->json([
            'server_id' => $server->id,
            'reports' => $fleet->scoreHistory($server),
        ]);
    }

    public function compare(Request $request, Server $server, MonitoringFleetService $fleet): JsonResponse
    {
        $leftId = (int) $request->query('left');
        $rightId = (int) $request->query('right');

        return response()->json($fleet->compareReports($server, $leftId, $rightId));
    }

    public function markAlertRead(MonitoringAlert $alert): JsonResponse
    {
        $alert->forceFill(['read_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function serverMetrics(Request $request, Server $server, ServerMetricsService $metrics): JsonResponse
    {
        $preset = (string) $request->query('preset', '24h');
        $range = $metrics->resolvePreset($preset);

        if ($request->filled('from') && $request->filled('to')) {
            $from = \Illuminate\Support\Carbon::parse((string) $request->query('from'));
            $to = \Illuminate\Support\Carbon::parse((string) $request->query('to'));
        } else {
            $from = $range['from'];
            $to = $range['to'];
        }

        $resolution = (string) $request->query('resolution', $range['resolution']);

        return response()->json($metrics->dashboard($server, $from, $to, $resolution));
    }

    public function installCommand(Server $server, DoctorInstallHelper $doctor, MonitorInstallHelper $monitor): JsonResponse
    {
        return response()->json([
            'server_id' => $server->id,
            'server_name' => $server->name,
            'local' => $doctor->localCommand($server),
            'remote' => $doctor->remoteCommand($server),
            'monitor' => $monitor->installCommand($server),
            'bootstrap_url' => $doctor->bootstrapUrl(),
        ]);
    }
}
