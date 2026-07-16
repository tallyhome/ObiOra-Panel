<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\Monitoring\MonitoringAlertService;
use App\Services\Monitoring\ServerPingService;
use App\Services\Realtime\RealtimeBroadcaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MonitorServersPingCommand extends Command
{
    protected $signature = 'obiora:monitor-ping {--server= : ID serveur specifique}';

    protected $description = 'Probe ICMP/TCP de tous les serveurs et enregistre la latence';

    public function handle(ServerPingService $pinger, MonitoringAlertService $alerts, RealtimeBroadcaster $realtime): int
    {
        try {
            $query = Server::query()->orderBy('id');
            if ($this->option('server')) {
                $query->whereKey((int) $this->option('server'));
            }

            $servers = $query->get();
            foreach ($servers as $server) {
                $sample = $pinger->probe($server);
                $status = $sample->success ? 'OK' : 'FAIL';
                $latency = $sample->latency_ms ?? '—';
                $this->line("{$server->name}: {$status} ({$latency} ms, {$sample->method})");

                if (! $sample->success) {
                    $alerts->recordServerOffline($server);
                }
            }

            $realtime->monitoringFleet();
        } catch (Throwable $e) {
            Log::warning('obiora:monitor-ping skipped', ['message' => $e->getMessage()]);

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
