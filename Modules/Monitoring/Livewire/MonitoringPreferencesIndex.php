<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\Support\PanelStorageAudit;
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

    /** @var array<string, mixed> */
    public array $storageAudit = [];

    public function mount(PanelStorageAudit $storage): void
    {
        if (request()->routeIs('monitoring.settings.retention')) {
            $this->activeTab = 'retention';
            $this->storageAudit = $storage->audit();
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

    public function refreshStorage(PanelStorageAudit $storage): void
    {
        $this->storageAudit = $storage->audit();
    }

    public function purgeStorage(string $action, PanelStorageAudit $storage): void
    {
        abort_unless(auth()->user()?->can('monitoring.manage'), 403);

        $message = match ($action) {
            'views' => sprintf('%d vue(s) compilée(s) supprimée(s).', $storage->clearCompiledViews()),
            'cache' => sprintf('%d fichier(s) cache supprimé(s).', $storage->clearFrameworkCache()),
            'logs' => sprintf('%d ancien(s) log(s) supprimé(s).', $storage->clearOldLogs()),
            'crash' => sprintf('%d fichier(s) Crash Analyzer supprimé(s).', $storage->clearCrashAnalyzerExports()),
            default => null,
        };

        if ($action === 'prune') {
            \Artisan::call('obiora:prune');
            $message = 'Purge monitoring exécutée (obiora:prune).';
        }

        if ($message === null) {
            $this->dispatch('notify', type: 'danger', message: 'Action inconnue.');

            return;
        }

        $this->storageAudit = $storage->audit();
        $this->dispatch('notify', type: 'success', message: $message);
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
            'canManage' => auth()->user()?->can('monitoring.manage') ?? false,
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
