<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoringAlert;
use App\Models\Server;
use App\Services\Monitoring\MonitoringFleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MonitoringFleetController extends Controller
{
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
}
