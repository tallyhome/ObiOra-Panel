<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Monitoring\MonitoringFleetService;
use App\Services\Monitoring\ServerPingService;
use App\Models\Server;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MonitoringStreamController extends Controller
{
    public function stream(MonitoringFleetService $fleet, ServerPingService $pinger): StreamedResponse
    {
        $interval = max(3, (int) config('obiora.monitoring.stream_interval_seconds', 5));

        return response()->stream(function () use ($fleet, $pinger, $interval) {
            @set_time_limit(0);

            while (! connection_aborted()) {
                foreach (Server::query()->orderBy('id')->get() as $server) {
                    if (connection_aborted()) {
                        break 2;
                    }
                    $pinger->probe($server);
                }

                $payload = json_encode([
                    'servers' => $fleet->fleetSnapshot(),
                    'alerts' => $fleet->unreadAlerts(10),
                    'at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE);

                echo 'event: fleet'."\n";
                echo 'data: '.$payload."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                sleep($interval);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
