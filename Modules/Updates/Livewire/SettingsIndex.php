<?php

declare(strict_types=1);

namespace Modules\Updates\Livewire;

use App\Jobs\RunSystemPackageUpdateJob;
use App\Models\UpdateHistory;
use App\Support\ChangelogParser;
use App\Services\Core\LicenseService;
use App\Services\Core\PanelUpdater;
use App\Services\Core\SystemMaintenance;
use App\Services\Core\UpdateManager;
use App\Services\Core\UpdateRecovery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

    public ?int $viewingOutputId = null;

    public string $viewingOutput = '';

    public bool $systemUpdateRunning = false;

    public ?string $systemMessage = null;

    public bool $systemSuccess = false;

    public string $systemOutput = '';

    public function mount(
        LicenseService $licenseService,
        UpdateManager $updateManager,
        SystemMaintenance $systemMaintenance,
        UpdateRecovery $updateRecovery,
    ): void {
        $updateRecovery->recoverStale(20);

        try {
            Artisan::call('up');
        } catch (\Throwable) {
            // best-effort — sortir du mode maintenance si une MAJ précédente l'a laissé actif
        }

        $this->loadLicense($licenseService);
        $this->updateInfo = $updateManager->checkForUpdates();
        $this->lastCheckedAt = now()->format('d/m/Y H:i:s');
        $this->setUpdateFeedback();
        $this->resumePendingUpdate();
        $this->loadSystemMaintenanceStatus($systemMaintenance);
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
        $this->pollSystemUpdateStatus();

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
                // La fin du log contient l'erreur réelle (le début n'est que
                // le rappel des étapes "[1/8] git fetch..." etc.).
                $tail = trim((string) $history->output);
                $tail = mb_substr($tail, max(0, mb_strlen($tail) - 400));
                $this->updateMessage .= ' — …'.$tail;
            }
            $this->dispatch('notify', type: 'danger', message: 'La mise à jour a échoué. Consultez le détail dans l\'historique ci-dessous.');

            return;
        }

        // Détection progression bloquée (ex. npm build à 58 %)
        $stuckMinutes = 25;
        if ($history->status === 'running'
            && (int) ($history->progress ?? 0) >= 50
            && $history->updated_at?->lt(now()->subMinutes($stuckMinutes))) {
            $stuckProgress = (int) ($history->progress ?? 0);
            $history->update([
                'status' => 'failed',
                'progress_message' => 'Mise à jour bloquée (progression inchangée depuis '.$stuckMinutes.' min)',
                'output' => trim((string) $history->output."\n\n[auto] Progression bloquée à {$stuckProgress}%."),
                'completed_at' => now(),
            ]);
            $this->updateRunning = false;
            $this->pendingHistoryId = null;
            $this->updateSuccess = false;
            $this->updateMessage = 'Mise à jour bloquée à '.$stuckProgress.' %. Cliquez sur Débloquer puis relancez, ou mettez à jour en SSH.';
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

    public function queueSystemUpdate(): void
    {
        abort_unless(auth()->user()?->can('updates.manage'), 403);

        if ($this->systemUpdateRunning) {
            return;
        }

        RunSystemPackageUpdateJob::dispatch();
        $this->systemUpdateRunning = true;
        $this->systemSuccess = true;
        $this->systemMessage = 'Mise à jour système lancée en arrière-plan. Cela peut prendre plusieurs minutes.';
        $this->dispatch('notify', type: 'info', message: $this->systemMessage);
    }

    public function scheduleSystemReboot(SystemMaintenance $maintenance): void
    {
        abort_unless(auth()->user()?->can('updates.manage'), 403);

        $result = $maintenance->scheduleReboot(60);
        $this->systemSuccess = $result['success'];
        $this->systemMessage = $result['message'];
        $this->systemOutput = $result['output'];
        $this->dispatch('notify', type: $result['success'] ? 'warning' : 'danger', message: $result['message']);
    }

    private function loadSystemMaintenanceStatus(SystemMaintenance $systemMaintenance): void
    {
        $this->pollSystemUpdateStatus();
    }

    private function pollSystemUpdateStatus(): void
    {
        /** @var array{running?: bool, message?: string, output?: string, success?: ?bool}|null $status */
        $status = Cache::get('obiora:system_update');

        if (! is_array($status)) {
            return;
        }

        $this->systemUpdateRunning = (bool) ($status['running'] ?? false);
        $this->systemMessage = (string) ($status['message'] ?? '');
        $this->systemOutput = (string) ($status['output'] ?? '');

        if (array_key_exists('success', $status) && $status['success'] !== null) {
            $this->systemSuccess = (bool) $status['success'];
        }
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

    public function cancelBlockedUpdate(UpdateRecovery $updateRecovery): void
    {
        abort_unless(auth()->user()?->can('updates.manage'), 403);

        $ids = UpdateHistory::query()
            ->whereIn('status', ['queued', 'running'])
            ->pluck('id');

        foreach ($ids as $id) {
            $history = UpdateHistory::query()->find($id);
            if ($history === null) {
                continue;
            }

            $history->update([
                'status' => 'failed',
                'progress' => max((int) $history->progress, 5),
                'progress_message' => 'Mise à jour annulée manuellement',
                'output' => trim((string) $history->output."\n\n[manual] Réinitialisation depuis le panel."),
                'completed_at' => now(),
            ]);
        }

        try {
            Artisan::call('optimize:clear');
            Artisan::call('up');
        } catch (\Throwable) {
            // best-effort
        }

        $this->updateRunning = false;
        $this->pendingHistoryId = null;
        $this->updateProgress = 0;
        $this->updateProgressMessage = '';
        $this->updateSuccess = false;
        $this->updateMessage = 'Mise à jour bloquée réinitialisée. Purgez le cache navigateur (Ctrl+F5) puis relancez si besoin.';
        $updateRecovery->recoverStale(0);

        $this->dispatch('notify', type: 'warning', message: $this->updateMessage);
    }

    public function showHistoryOutput(int $historyId): void
    {
        $history = UpdateHistory::query()->find($historyId);
        $this->viewingOutputId = $historyId;
        $this->viewingOutput = trim((string) ($history?->output ?? '')) ?: 'Aucune sortie enregistrée.';
    }

    public function closeHistoryOutput(): void
    {
        $this->viewingOutputId = null;
        $this->viewingOutput = '';
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

    public function render(SystemMaintenance $systemMaintenance, ChangelogParser $changelog)
    {
        $latestVersion = (string) ($this->updateInfo['latest'] ?? '');
        $availableNotes = $latestVersion !== '' ? $changelog->notesForVersion($latestVersion) : null;

        return view('updates::livewire.settings-index', [
            'history' => UpdateHistory::query()->latest()->limit(10)->get(),
            'licenseEnabled' => (bool) config('license.enabled', false),
            'adminLicenceUrl' => (string) config('license.admin_licence_url'),
            'systemInfo' => $systemMaintenance->detectPackageManager(),
            'changelogSections' => $changelog->sections(6),
            'availableReleaseNotes' => $availableNotes,
        ]);
    }
}
