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

    public string $installationUuid = '';

    public string $currentPlan = 'free';

    public string $licenseStatus = 'inactive';

    public function mount(LicenseService $licenseService, UpdateManager $updateManager): void
    {
        $this->loadLicense($licenseService);
        $this->updateInfo = $updateManager->checkForUpdates();
        $this->setUpdateFeedback();
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
        $this->setUpdateFeedback();
    }

    public function applyUpdate(PanelUpdater $panelUpdater, UpdateManager $updateManager): void
    {
        abort_unless(auth()->user()?->can('updates.manage'), 403);

        try {
            $result = $panelUpdater->apply();
            $this->updateSuccess = $result['success'];
            $this->updateMessage = $result['message'];

            if (! $result['success'] && $result['output'] !== '') {
                $this->updateMessage .= ' — '.mb_substr($result['output'], 0, 200);
            }
        } catch (\Throwable $e) {
            $this->updateSuccess = false;
            $this->updateMessage = 'Erreur : '.$e->getMessage();
        }

        $this->updateInfo = $updateManager->checkForUpdates(fresh: true);
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
