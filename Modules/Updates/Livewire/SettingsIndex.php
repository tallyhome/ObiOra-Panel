<?php

declare(strict_types=1);

namespace Modules\Updates\Livewire;

use App\Models\UpdateHistory;
use App\Services\Core\LicenseService;
use App\Services\Core\PanelUpdater;
use App\Services\Core\UpdateManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Licence & Mises à jour')]
final class SettingsIndex extends Component
{
    public string $licenseKey = '';

    /** @var array<string, mixed> */
    public array $updateInfo = [];

    public ?string $licenseMessage = null;

    public bool $licenseSuccess = false;

    public ?string $updateMessage = null;

    public bool $updateSuccess = false;

    public ?string $lastCheckedAt = null;

    public string $installationUuid = '';

    public string $currentPlan = 'free';

    public string $licenseStatus = 'inactive';

    public ?int $pendingHistoryId = null;

    public bool $updateRunning = false;

    public int $updateProgress = 0;

    public string $updateProgressMessage = '';

    public function mount(LicenseService $licenseService, UpdateManager $updateManager): void
    {
        $this->loadLicense($licenseService);
        $this->updateInfo = $updateManager->checkForUpdates();
        $this->lastCheckedAt = now()->format('d/m/Y H:i:s');
        $this->setUpdateFeedback();
        $this->resumePendingUpdate();
    }

    public function activateLicense(LicenseService $licenseService): void
    {
        $this->authorize('license.manage');

        $result = $licenseService->activate($this->licenseKey);
        $this->licenseSuccess = $result['success'];
        $this->licenseMessage = $result['message'];
        $this->loadLicense($licenseService);
    }

    public function refreshLicense(LicenseService $licenseService): void
    {
        $this->authorize('license.manage');

        $result = $licenseService->refresh();
        $this->licenseSuccess = $result['success'];
        $this->licenseMessage = $result['message'];
        $this->loadLicense($licenseService);
    }

    public function checkUpdates(UpdateManager $updateManager): void
    {
        $this->updateInfo = $updateManager->checkForUpdates(fresh: true);
        $this->lastCheckedAt = now()->format('d/m/Y H:i:s');
        $this->setUpdateFeedback();

        $type = ($this->updateInfo['available'] ?? false)
            ? 'warning'
            : ($this->updateSuccess ? 'success' : 'danger');

        $this->dispatch('notify', type: $type, message: $this->updateMessage ?? 'Vérification terminée.');
    }

    public function applyUpdate(PanelUpdater $panelUpdater): void
    {
        abort_unless(auth()->user()?->can('updates.manage'), 403);

        if ($this->updateRunning) {
            return;
        }

        $result = $panelUpdater->queueUpdate();

        $this->updateSuccess = $result['success'];
        $this->updateMessage = $result['message'];
        $this->pendingHistoryId = $result['history_id'];
        $this->updateRunning = $result['success'] && $result['history_id'] !== null;
        $this->updateProgress = $this->updateRunning ? 2 : 0;
        $this->updateProgressMessage = $this->updateRunning ? 'Mise en file d\'attente…' : '';

        $this->dispatch('notify', type: $result['success'] ? 'info' : 'danger', message: $result['message']);
    }

    public function pollUpdateStatus(UpdateManager $updateManager): void
    {
        if ($this->pendingHistoryId === null) {
            $this->updateRunning = false;

            return;
        }

        $history = UpdateHistory::query()->find($this->pendingHistoryId);

        if ($history === null || in_array($history->status, ['completed', 'failed'], true)) {
            $this->updateRunning = false;
            $this->pendingHistoryId = null;
            $this->updateInfo = $updateManager->checkForUpdates(fresh: true);
            $this->lastCheckedAt = now()->format('d/m/Y H:i:s');

            if ($history === null) {
                $this->setUpdateFeedback();

                return;
            }

            if ($history->status === 'completed') {
                $this->updateSuccess = true;
                $this->updateMessage = "Mise à jour vers v{$history->to_version} terminée. Rechargez la page (Ctrl+F5).";
                $this->dispatch('notify', type: 'success', message: $this->updateMessage);

                return;
            }

            $this->updateSuccess = false;
            $this->updateMessage = 'Échec de la mise à jour.';
            if (! empty($history->output)) {
                $this->updateMessage .= ' — '.mb_substr((string) $history->output, 0, 200);
            }
            $this->dispatch('notify', type: 'danger', message: $this->updateMessage);

            return;
        }

        // Toujours 'queued' ou 'running' : on continue de sonder.
        $this->updateProgress = (int) ($history->progress ?? 0);
        $this->updateProgressMessage = (string) ($history->progress_message ?? '');
        $this->updateMessage = $this->updateProgressMessage !== ''
            ? $this->updateProgressMessage
            : ($history->status === 'running'
                ? 'Mise à jour en cours d\'exécution (composer, migrations, build)…'
                : 'Mise à jour en file d\'attente, démarrage imminent…');
        $this->updateSuccess = true;
    }

    private function resumePendingUpdate(): void
    {
        $pending = UpdateHistory::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest()
            ->first();

        if ($pending === null) {
            return;
        }

        $this->pendingHistoryId = $pending->id;
        $this->updateRunning = true;
        $this->updateSuccess = true;
        $this->updateProgress = (int) ($pending->progress ?? 0);
        $this->updateProgressMessage = (string) ($pending->progress_message ?? '');
        $this->updateMessage = $this->updateProgressMessage !== ''
            ? $this->updateProgressMessage
            : ($pending->status === 'running'
                ? 'Mise à jour en cours d\'exécution (composer, migrations, build)…'
                : 'Mise à jour en file d\'attente, démarrage imminent…');
    }

    private function setUpdateFeedback(): void
    {
        if ($this->updateInfo['available'] ?? false) {
            $latest = $this->updateInfo['latest'] ?? '?';
            $behind = $this->updateInfo['commits_behind'] ?? 0;
            $this->updateMessage = $behind > 0
                ? "Mise à jour disponible (v{$latest}) — {$behind} commit(s) en retard sur main."
                : "Mise à jour v{$latest} disponible.";
            $this->updateSuccess = true;

            return;
        }

        if (! empty($this->updateInfo['error'])) {
            $this->updateMessage = $this->updateInfo['error'];
            $this->updateSuccess = false;

            return;
        }

        $this->updateMessage = 'Vous êtes à jour (v'.$this->updateInfo['current'].').';
        $this->updateSuccess = true;
    }

    private function loadLicense(LicenseService $licenseService): void
    {
        $this->installationUuid = $licenseService->getInstallationUuid();
        $license = $licenseService->current();
        $this->currentPlan = $license?->plan ?? 'free';
        $this->licenseStatus = $license?->status ?? 'inactive';

        if ($license?->license_key) {
            $this->licenseKey = (string) $license->license_key;
        }
    }

    public function render()
    {
        return view('updates::livewire.settings-index', [
            'history' => UpdateHistory::query()->latest()->limit(10)->get(),
            'licenseEnabled' => (bool) config('license.enabled', false),
            'adminLicenceUrl' => (string) config('license.admin_licence_url'),
        ]);
    }
}
