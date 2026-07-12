<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Models\StatusPageSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Status page')]
final class MonitoringStatusPageSettings extends Component
{
    public bool $isEnabled = true;

    public string $title = 'ObiOra Status';

    public bool $noindex = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);

        $settings = StatusPageSetting::current();
        $this->isEnabled = $settings->is_enabled;
        $this->title = $settings->title;
        $this->noindex = $settings->noindex;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        StatusPageSetting::current()->update([
            'is_enabled' => $this->isEnabled,
            'title' => $this->title,
            'noindex' => $this->noindex,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Status page mise à jour.');
    }

    public function render()
    {
        return view('monitoring::livewire.monitoring-status-page-settings', [
            'publicUrl' => route('status.index'),
        ]);
    }
}
