<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\Server;
use App\Services\Monitoring\MaintenanceWindowService;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Maintenance')]
final class MonitoringMaintenanceIndex extends Component
{
    public bool $showModal = false;

    public string $resourceType = 'all';

    /** @var list<int> */
    public array $resourceIds = [];

    public string $startsAt = '';

    public string $endsAt = '';

    public string $note = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addHours(2)->format('Y-m-d\TH:i');
    }

    public function openModal(): void
    {
        $this->authorizeManage();
        $this->showModal = true;
    }

    public function save(MaintenanceWindowService $service): void
    {
        $this->authorizeManage();

        $this->validate([
            'resourceType' => ['required', 'in:all,server,monitor'],
            'resourceIds' => ['array'],
            'resourceIds.*' => ['integer'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->schedule(
            resourceType: $this->resourceType,
            resourceIds: $this->resourceType === 'all' ? null : array_values(array_map('intval', $this->resourceIds)),
            startsAt: \Illuminate\Support\Carbon::parse($this->startsAt),
            endsAt: \Illuminate\Support\Carbon::parse($this->endsAt),
            note: $this->note !== '' ? $this->note : null,
            creator: auth()->user(),
        );

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('notify', type: 'success', message: 'Fenêtre de maintenance planifiée.');
    }

    public function cancelWindow(int $windowId, MaintenanceWindowService $service): void
    {
        $this->authorizeManage();

        $window = MaintenanceWindow::query()->findOrFail($windowId);
        $service->cancel($window);
        $this->dispatch('notify', type: 'success', message: 'Maintenance annulée.');
    }

    public function render(MaintenanceWindowService $service)
    {
        $windows = $service->upcomingAndActive()->map(fn (MaintenanceWindow $window) => [
            'id' => $window->id,
            'resource_type' => $window->resource_type,
            'resource_ids' => $window->resource_ids ?? [],
            'starts_at' => UserTimezone::format($window->starts_at, 'd/m/Y H:i'),
            'ends_at' => UserTimezone::format($window->ends_at, 'd/m/Y H:i'),
            'note' => $window->note,
            'active' => $window->isActive(),
            'scheduled' => $window->isScheduled(),
        ]);

        return view('monitoring::livewire.monitoring-maintenance-index', [
            'windows' => $windows,
            'servers' => Server::query()->where('is_master', false)->orderBy('name')->get(['id', 'name']),
            'monitors' => Monitor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => auth()->user()?->can('monitoring.manage') ?? false,
            'timezoneFooter' => UserTimezone::label(),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);
    }

    private function resetForm(): void
    {
        $this->resourceType = 'all';
        $this->resourceIds = [];
        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addHours(2)->format('Y-m-d\TH:i');
        $this->note = '';
    }
}
