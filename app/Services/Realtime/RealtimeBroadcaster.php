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
use Illuminate\Support\Facades\Log;
use Throwable;

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

        try {
            $metrics = $this->metrics->collect($server);
            $serviceList = $this->services->list($server);

            event(new DashboardMetricsUpdated($server->id, $metrics, $serviceList));
        } catch (Throwable $e) {
            $this->logFailure('dashboard', $e);
        }
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

        try {
            event(new ServiceStateChanged($server->id, $service, $action, $success, $output));
        } catch (Throwable $e) {
            $this->logFailure('serviceState', $e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function progress(int $serverId, string $scope, string $key, array $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            event(new ProgressUpdated($serverId, $scope, $key, $payload));
        } catch (Throwable $e) {
            $this->logFailure('progress', $e);
        }
    }

    public function monitoringFleet(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            event(new MonitoringFleetUpdated(
                $this->fleet->fleetSnapshot(),
                $this->fleet->unreadAlerts(10),
            ));
        } catch (Throwable $e) {
            $this->logFailure('monitoringFleet', $e);
        }
    }

    private function logFailure(string $scope, Throwable $e): void
    {
        Realtime::resetReachableCache();
        Log::warning('Realtime broadcast skipped', [
            'scope' => $scope,
            'message' => $e->getMessage(),
        ]);
    }
}
