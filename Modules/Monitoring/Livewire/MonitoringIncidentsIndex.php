<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\MonitoringAlert;
use App\Models\MonitoringIncident;
use App\Services\Monitoring\MonitoringIncidentService;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Incidents')]
final class MonitoringIncidentsIndex extends Component
{
    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public string $viewTab = 'incidents';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        if (request()->routeIs('monitoring.incidents.logs')) {
            $this->viewTab = 'logs';
        }
    }

    public function markResolved(int $incidentId): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);

        $incident = MonitoringIncident::query()->find($incidentId);

        if ($incident !== null) {
            $incident->forceFill([
                'status' => 'resolved',
                'recovered_at' => now(),
            ])->save();
            $this->dispatch('notify', type: 'success', message: 'Incident marqué comme résolu.');

            return;
        }

        $alert = MonitoringAlert::query()->findOrFail($incidentId);
        $alert->forceFill(['read_at' => now()])->save();
        $this->dispatch('notify', type: 'success', message: 'Incident marqué comme résolu.');
    }

    public function render(MonitoringIncidentService $incidents)
    {
        $all = $incidents->allIncidents(100);
        $filtered = collect($all)->filter(function (array $row) {
            if ($this->statusFilter === 'open' && $row['status'] !== 'open') {
                return false;
            }
            if ($this->statusFilter === 'resolved' && $row['status'] !== 'resolved') {
                return false;
            }
            if ($this->typeFilter === 'servers' && $row['resource_type'] !== 'server') {
                return false;
            }
            if ($this->typeFilter === 'monitors' && $row['resource_type'] !== 'monitor') {
                return false;
            }

            return true;
        })->values()->all();

        $openCount = collect($all)->where('status', 'open')->count();

        return view('monitoring::livewire.monitoring-incidents-index', [
            'incidents' => $filtered,
            'notificationLogs' => $incidents->notificationLogs(100),
            'openCount' => $openCount,
            'totalCount' => count($filtered),
            'canManage' => auth()->user()?->can('monitoring.manage') ?? false,
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ]);
    }
}
