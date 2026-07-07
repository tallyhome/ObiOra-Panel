<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\Events\DashboardMetricsUpdated;
use App\Events\MonitoringFleetUpdated;
use App\Events\ProgressUpdated;
use App\Events\ServiceStateChanged;
use App\Models\Server;
use App\Services\Monitoring\MonitoringFleetService;
use App\Services\System\MetricsCollector;
use App\Services\System\ServiceManager;
use App\Support\Realtime;

final class RealtimeBroadcaster
{
    public function __construct(
        private readonly MetricsCollector $metrics,
        private readonly ServiceManager $services,
        private readonly MonitoringFleetService $fleet,
    ) {}

    public function isEnabled(): bool
    {
        return Realtime::enabled();
    }

    public function dashboard(Server $server): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $metrics = $this->metrics->collect($server);
        $serviceList = $this->services->list($server);

        event(new DashboardMetricsUpdated($server->id, $metrics, $serviceList));
    }

    public function serviceState(
        Server $server,
        string $service,
        string $action,
        bool $success,
        string $output,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        event(new ServiceStateChanged($server->id, $service, $action, $success, $output));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function progress(int $serverId, string $scope, string $key, array $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        event(new ProgressUpdated($serverId, $scope, $key, $payload));
    }

    public function monitoringFleet(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        event(new MonitoringFleetUpdated(
            $this->fleet->fleetSnapshot(),
            $this->fleet->unreadAlerts(10),
        ));
    }
}
