<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\DTOs\SshConnection;
use App\Enums\ServerType;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Deploy\RemoteDeployLauncher;
use App\Services\Diagnostics\RemoteOsDetector;
use App\Services\Diagnostics\ServerSshKeyService;
use App\Services\Monitoring\MonitorAgentDeployProgressService;
use App\Services\Monitoring\MonitorAgentDeployRunner;
use App\Services\Monitoring\MonitorAgentRemoteDeployService;
use App\Services\Monitoring\MonitoringDashboardService;
use App\Support\MonitorInstallHelper;
use App\Support\UserTimezone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Monitoring — Serveurs')]
final class MonitoringServersIndex extends Component
{
    public bool $showAddModal = false;

    public bool $showInstallModal = false;

    public bool $showRemovedModal = false;

    public string $installMode = 'ssh';

    public ?int $installServerId = null;

    public string $installCommand = '';

    public string $removedServerName = '';

    public string $uninstallCommand = '';

    public string $newName = '';

    public string $newIp = '';

    public string $newTagsInput = '';

    public string $sshHost = '';

    public int $sshPort = 22;

    public string $sshUser = 'root';

    public string $sshPassword = '';

    public ?string $sshTestResult = null;

    public bool $sshTestOk = false;

    public bool $deployRunning = false;

    public int $deployProgress = 0;

    public string $deployProgressMessage = '';

    /** @var list<string> */
    public array $deployConsole = [];

    public ?string $deployError = null;

    public bool $deployFinished = false;

    public ?bool $deploySuccess = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('monitoring.view'), 403);

        if (request()->boolean('add')) {
            $this->showAddModal = true;
        }
    }

    public function openAddModal(): void
    {
        $this->authorizeManage();
        $this->resetAddForm();
        $this->showAddModal = true;
    }

    public function createServer(ServerManager $servers, MonitorInstallHelper $install): void
    {
        $this->authorizeManage();

        $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newIp' => ['nullable', 'ip'],
        ]);

        $tags = $this->parseTags($this->newTagsInput);

        $server = $servers->createRemote([
            'name' => $this->newName,
            'ip_address' => $this->newIp !== '' ? $this->newIp : '127.0.0.1',
            'hostname' => $this->newIp !== '' ? $this->newIp : $this->newName,
            'type' => ServerType::Vps,
            'tags' => $tags,
        ]);

        $this->showAddModal = false;
        $this->openInstallWizard($server, $install);
        $this->resetAddForm();
    }

    public function closeInstallModal(): void
    {
        $this->showInstallModal = false;
        $this->installServerId = null;
        $this->installCommand = '';
        $this->resetDeployState();
    }

    public function deleteServer(int $serverId, ServerManager $servers, MonitorInstallHelper $install): void
    {
        $this->authorizeManage();

        $server = Server::query()->findOrFail($serverId);

        try {
            $this->removedServerName = $server->name;
            $this->uninstallCommand = $install->uninstallCommand();
            $servers->delete($server);
            $this->showRemovedModal = true;
            $this->dispatch('notify', type: 'success', message: "Serveur « {$this->removedServerName} » retiré du panel.");
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function showInstallFor(int $serverId, MonitorInstallHelper $install): void
    {
        $this->authorizeManage();
        $server = Server::query()->findOrFail($serverId);
        $this->openInstallWizard($server, $install);
    }

    public function updatedSshHost(): void
    {
        $this->resetSshTest();
    }

    public function updatedSshPort(): void
    {
        $this->resetSshTest();
    }

    public function updatedSshUser(): void
    {
        $this->resetSshTest();
    }

    public function testSshConnection(
        ServerSshKeyService $sshKeys,
        MonitorAgentRemoteDeployService $deploy,
        RemoteOsDetector $osDetector,
    ): void {
        $this->authorizeManage();
        $this->validateSshForm();

        $ssh = $this->resolveConnection($sshKeys);

        if ($ssh === null) {
            $this->sshTestOk = false;
            $this->sshTestResult = 'Saisissez le mot de passe SSH pour tester la connexion (première installation).';

            return;
        }

        $result = $deploy->testConnection($ssh);
        $this->sshTestOk = $result['success'];
        $this->sshTestResult = $result['success']
            ? ($result['message'] ?: 'Connexion réussie.')
            : ($result['message'] ?: 'Connexion SSH refusée.');

        if ($result['success'] && $this->installServerId !== null) {
            $server = Server::query()->find($this->installServerId);
            if ($server !== null) {
                $os = $osDetector->detect($ssh);
                if ($os !== null) {
                    $server->update([
                        'os_name' => $os['name'],
                        'os_version' => $os['version'],
                    ]);
                    if ($this->sshTestResult !== null) {
                        $this->sshTestResult .= ' — OS : '.$os['name'].($os['version'] ? ' '.$os['version'] : '');
                    }
                }
            }
        }

        $this->deployError = null;
    }

    public function deployMonitorAgent(
        MonitorAgentDeployProgressService $progress,
        MonitorAgentDeployRunner $runner,
        RemoteDeployLauncher $launcher,
    ): void {
        $this->authorizeManage();
        $this->validateSshForm();

        if (! $this->sshTestOk) {
            $this->deployError = 'Testez d\'abord la connexion SSH.';
            $this->dispatch('notify', type: 'warning', message: $this->deployError);

            return;
        }

        if ($this->installServerId === null || $this->deployRunning) {
            return;
        }

        try {
            $runner->storeBootstrapPassword($this->installServerId, $this->sshPassword);
            $progress->start($this->installServerId);
            $progress->appendLog($this->installServerId, 'Lancement de l\'installation automatique…');
            $launcher->launchMonitorAgent($this->installServerId, $this->sshHost, $this->sshPort, $this->sshUser);

            $this->deployRunning = true;
            $this->deployFinished = false;
            $this->deployProgress = 5;
            $this->deployProgressMessage = 'Connexion SSH et installation de l\'agent métriques…';
            $this->deployConsole = ['['.now()->format('H:i:s').'] Installation démarrée…'];
            $this->deployError = null;
            $this->sshPassword = '';
        } catch (\Throwable $e) {
            if ($this->installServerId !== null) {
                $progress->cancel($this->installServerId, 'Échec au lancement : '.$e->getMessage());
            }
            $this->deployError = $e->getMessage();
            $this->deployRunning = false;
            $this->dispatch('notify', type: 'danger', message: $this->deployError);
        }
    }

    public function pollDeploy(MonitorAgentDeployProgressService $progress): void
    {
        if ($this->installServerId === null) {
            return;
        }

        $status = $progress->status($this->installServerId);

        if (! is_array($status)) {
            if ($this->deployRunning) {
                $this->resetDeployState();
            }

            return;
        }

        $this->deployProgress = (int) ($status['progress'] ?? 0);
        $this->deployProgressMessage = (string) ($status['message'] ?? '');
        $this->deployConsole = is_array($status['console'] ?? null) ? $status['console'] : [];
        $this->deployRunning = (bool) ($status['running'] ?? false);

        if ($this->deployRunning && $progress->isStale($this->installServerId)) {
            $progress->cancel($this->installServerId, 'Installation interrompue (processus bloqué).');
            $this->deployRunning = false;
            $this->deployError = 'Installation bloquée — consultez storage/logs/deploy.log.';
        }

        if (! $this->deployRunning && array_key_exists('success', $status)) {
            $ok = (bool) $status['success'];
            $this->deployFinished = true;
            $this->deploySuccess = $ok;

            if ($ok) {
                $progress->clear($this->installServerId);
                $this->dispatch('notify', type: 'success', message: (string) ($status['message'] ?? 'Agent installé.'));
            } else {
                $this->deployError = (string) ($status['message'] ?? 'Échec installation.');
                $this->dispatch('notify', type: 'danger', message: $this->deployError);
            }
        }
    }

    public function render(MonitoringDashboardService $dashboard)
    {
        return view('monitoring::livewire.monitoring-servers-index', [
            'servers' => $dashboard->serverRows(),
            'canManageServers' => auth()->user()?->can('servers.manage') ?? false,
            'timezoneFooter' => UserTimezone::label(),
            'nowLabel' => UserTimezone::now()->format('d/m/Y H:i:s'),
        ]);
    }

    private function openInstallWizard(Server $server, MonitorInstallHelper $install): void
    {
        $this->installServerId = $server->id;
        $this->installCommand = $install->installCommand($server);
        $this->sshHost = $server->ip_address !== '127.0.0.1' ? $server->ip_address : '';
        $this->sshPort = 22;
        $this->sshUser = 'root';
        $this->sshPassword = '';
        $this->installMode = 'ssh';
        $this->resetSshTest();
        $this->resetDeployState();
        $this->showInstallModal = true;

        $deployMeta = ($server->metadata ?? [])['monitor_agent'] ?? null;
        if (is_array($deployMeta) && isset($deployMeta['remote_host'])) {
            $this->sshHost = (string) $deployMeta['remote_host'];
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('servers.manage'), 403);
    }

    private function resetAddForm(): void
    {
        $this->newName = '';
        $this->newIp = '';
        $this->newTagsInput = '';
    }

    private function resetSshTest(): void
    {
        $this->sshTestOk = false;
        $this->sshTestResult = null;
    }

    private function resetDeployState(): void
    {
        $this->deployRunning = false;
        $this->deployFinished = false;
        $this->deploySuccess = null;
        $this->deployProgress = 0;
        $this->deployProgressMessage = '';
        $this->deployConsole = [];
        $this->deployError = null;
    }

    private function validateSshForm(): void
    {
        $this->validate([
            'sshHost' => ['required', 'string', 'max:255'],
            'sshPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'sshUser' => ['required', 'string', 'max:64'],
        ]);
    }

    private function resolveConnection(ServerSshKeyService $sshKeys): ?SshConnection
    {
        if ($this->installServerId === null) {
            return null;
        }

        $server = Server::query()->find($this->installServerId);

        if ($server === null) {
            return null;
        }

        if ($sshKeys->hasKey($server) && $sshKeys->isInstalledOnRemote($server)) {
            return $sshKeys->connection($server, $this->sshHost, $this->sshPort, $this->sshUser);
        }

        if ($this->sshPassword === '') {
            return null;
        }

        return new SshConnection(
            host: $this->sshHost,
            port: $this->sshPort,
            username: $this->sshUser,
            password: $this->sshPassword,
        );
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
