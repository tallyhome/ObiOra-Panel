<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Services\Monitoring\MonitoringFleetService;
use App\Services\Monitoring\MonitoringWitnessService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Flotte avancée')]
final class MonitoringIndex extends Component
{
    public string $panelUrl = '';

    public bool $realtimeEnabled = false;

    public function mount(): void
    {
        $this->panelUrl = rtrim((string) config('app.url'), '/');
        $this->realtimeEnabled = \App\Support\Realtime::enabled();
    }

    public function render(MonitoringFleetService $fleet, MonitoringWitnessService $witness)
    {
        return view('monitoring::livewire.monitoring-index', [
            'initialFleet' => $fleet->fleetSnapshot(),
            'initialAlerts' => $fleet->unreadAlerts(),
            'witnessSummary' => $witness->fleetSummary(),
            'witnessAnomalies' => $witness->anomalyCount(),
        ]);
    }
}
