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
    public string $timezone = 'UTC';

    public string $previewTime = '';

    public function mount(): void
    {
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
