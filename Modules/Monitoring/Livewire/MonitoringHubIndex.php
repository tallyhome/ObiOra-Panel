<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Enums\ServerType;
use App\Services\Core\ServerManager;
use App\Services\Monitoring\MonitoringDashboardService;
use App\Services\Monitoring\MonitoringWitnessService;
use App\Support\MonitorInstallHelper;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Dashboard')]
final class MonitoringHubIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);
    }

    public function render(MonitoringDashboardService $dashboard, MonitoringWitnessService $witness)
    {
        return view('monitoring::livewire.monitoring-hub-index', [
            'summary' => $dashboard->summary(),
            'recentServers' => $dashboard->recentServers(5),
            'recentMonitors' => $dashboard->recentMonitors(5),
            'openIncidents' => $dashboard->openIncidents(10),
            'witnessSummary' => $witness->fleetSummary(),
            'witnessAnomalies' => $witness->anomalyCount(),
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
            'canManageServers' => auth()->user()?->can('servers.manage') ?? false,
        ]);
    }
}
