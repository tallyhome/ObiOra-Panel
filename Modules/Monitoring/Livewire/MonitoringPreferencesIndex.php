<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Support\TimezoneCatalog;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Préférences')]
final class MonitoringPreferencesIndex extends Component
{
    public string $activeTab = 'timezone';

    public string $timezone = 'UTC';

    public string $previewTime = '';

    public function mount(): void
    {
        if (request()->routeIs('monitoring.settings.retention')) {
            $this->activeTab = 'retention';
        }

        $this->timezone = UserTimezone::resolve();
        $this->refreshPreview();
    }

    public function updatedTimezone(): void
    {
        $this->refreshPreview();
    }

    public function save(): void
    {
        abort_unless(TimezoneCatalog::isValid($this->timezone), 422);

        $user = auth()->user();
        abort_if($user === null, 403);

        $user->forceFill(['timezone' => $this->timezone])->save();
        $this->refreshPreview();
        $this->dispatch('notify', type: 'success', message: 'Fuseau horaire enregistré.');
    }

    public function render()
    {
        return view('monitoring::livewire.monitoring-preferences-index', [
            'timezoneChoices' => TimezoneCatalog::choices(),
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
            'retention' => [
                'ping_days' => (int) config('monitoring.retention_days', 60),
                'sample_days' => (int) config('monitoring.sample_retention_days', 60),
                'check_days' => (int) config('monitoring.check_retention_days', 60),
                'prometheus_enabled' => (bool) config('monitoring.prometheus.enabled', false),
            ],
        ]);
    }

    private function refreshPreview(): void
    {
        if (! TimezoneCatalog::isValid($this->timezone)) {
            $this->previewTime = '—';

            return;
        }

        $this->previewTime = now()->timezone($this->timezone)->format('d M Y, H:i:s').' ('.$this->timezone.')';
    }
}
