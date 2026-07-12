<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Moniteurs')]
final class MonitoringMonitorsIndex extends Component
{
    public bool $showAddModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'https';

    public string $target = '';

    public ?int $port = null;

    public string $keyword = '';

    public bool $keywordPresent = true;

    public string $dnsRecordType = 'A';

    public int $intervalSeconds = 300;

    public string $tagsInput = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        if (request()->boolean('add')) {
            $this->openAddModal();
        }
    }

    public function openAddModal(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showAddModal = true;
        $this->editingId = null;
    }

    public function editMonitor(int $monitorId): void
    {
        $this->authorizeManage();
        $monitor = Monitor::query()->findOrFail($monitorId);
        $this->editingId = $monitor->id;
        $this->name = $monitor->name;
        $this->type = $monitor->type->value;
        $this->target = $monitor->target;
        $this->port = $monitor->port;
        $this->keyword = $monitor->keyword ?? '';
        $this->keywordPresent = (bool) $monitor->keyword_present;
        $this->dnsRecordType = $monitor->type === MonitorType::Dns
            ? ($monitor->keyword ?: 'A')
            : 'A';
        $this->intervalSeconds = $monitor->interval_seconds;
        $this->tagsInput = implode(', ', $monitor->tags ?? []);
        $this->showAddModal = true;
    }

    public function saveMonitor(): void
    {
        $this->authorizeManage();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', array_column(MonitorType::cases(), 'value'))],
            'target' => ['required', 'string', 'max:2048'],
            'intervalSeconds' => ['required', 'integer', 'in:60,300,600,1800'],
        ];

        if ($this->type === 'port') {
            $rules['port'] = ['required', 'integer', 'min:1', 'max:65535'];
        }

        if ($this->type === 'keyword') {
            $rules['keyword'] = ['required', 'string', 'max:512'];
        }

        $this->validate($rules);

        $keyword = match ($this->type) {
            'keyword' => $this->keyword,
            'dns' => strtoupper($this->dnsRecordType),
            default => null,
        };

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'target' => trim($this->target),
            'port' => $this->type === 'port' ? $this->port : null,
            'keyword' => $keyword,
            'keyword_present' => $this->type === 'keyword' ? $this->keywordPresent : true,
            'interval_seconds' => $this->intervalSeconds,
            'tags' => $this->parseTags($this->tagsInput),
        ];

        if ($this->editingId !== null) {
            Monitor::query()->whereKey($this->editingId)->update($data);
            $this->dispatch('notify', type: 'success', message: 'Moniteur mis à jour.');
        } else {
            Monitor::query()->create($data);
            $this->dispatch('notify', type: 'success', message: 'Moniteur créé.');
        }

        $this->showAddModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $monitorId): void
    {
        $this->authorizeManage();
        $monitor = Monitor::query()->findOrFail($monitorId);
        $monitor->update(['is_active' => ! $monitor->is_active]);
    }

    public function deleteMonitor(int $monitorId): void
    {
        $this->authorizeManage();
        Monitor::query()->whereKey($monitorId)->delete();
        $this->dispatch('notify', type: 'success', message: 'Moniteur supprimé.');
    }

    public function render()
    {
        $monitors = Monitor::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Monitor $monitor) => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'type' => $monitor->type->label(),
                'target' => $monitor->displayTarget(),
                'status' => $monitor->last_status ?? 'unknown',
                'response_ms' => $monitor->last_response_ms,
                'last_checked' => UserTimezone::format($monitor->last_checked_at, 'd/m/Y H:i:s'),
                'is_active' => $monitor->is_active,
            ]);

        return view('monitoring::livewire.monitoring-monitors-index', [
            'monitors' => $monitors,
            'typeChoices' => MonitorType::choices(),
            'intervalChoices' => $this->intervalChoices(),
            'canManage' => auth()->user()?->can('monitoring.manage') ?? false,
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->type = 'https';
        $this->target = '';
        $this->port = null;
        $this->keyword = '';
        $this->keywordPresent = true;
        $this->dnsRecordType = 'A';
        $this->intervalSeconds = 300;
        $this->tagsInput = '';
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function intervalChoices(): array
    {
        return [
            ['value' => 60, 'label' => 'Chaque minute'],
            ['value' => 300, 'label' => 'Chaque 5 minutes'],
            ['value' => 600, 'label' => 'Chaque 10 minutes'],
            ['value' => 1800, 'label' => 'Chaque 30 minutes'],
        ];
    }

    /**
     * @return list<string>
     */
    private function parseTags(string $input): array
    {
        $parts = preg_split('/[,\n]+/', $input) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
