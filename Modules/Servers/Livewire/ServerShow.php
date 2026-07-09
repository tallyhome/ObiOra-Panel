<?php

declare(strict_types=1);

namespace Modules\Servers\Livewire;

use App\DTOs\SshConnection;
use App\Livewire\Concerns\AuthorizesPanelAccess;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Diagnostics\ServerSshKeyService;
use App\Services\Diagnostics\RemoteOsDetector;
use App\Services\Deploy\RemoteDeployLauncher;
use App\Services\Servers\SlaveDeployProgressService;
use App\Services\Servers\SlaveDeployRunner;
use App\Services\Servers\SlaveRemoteDeployService;
use App\Support\DoctorInstallHelper;
use App\Support\SlaveInstallHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Détail serveur')]
final class ServerShow extends Component
{
    use AuthorizesPanelAccess;

    public Server $server;

    public string $doctorRemoteCommand = '';

    public string $slaveRemoteCommand = '';

    public string $sshHost = '';

    public int $sshPort = 22;

    public string $sshUser = 'root';

    public string $sshPassword = '';

    public bool $sshTestOk = false;

    public ?string $sshTestResult = null;

    public bool $deployRunning = false;

    public int $deployProgress = 0;

    public string $deployProgressMessage = '';

    /** @var list<string> */
    public array $deployConsole = [];

    public ?string $deployError = null;

    public bool $deployFinished = false;

    public bool $deployDismissed = true;

    public bool $showSshInstallPanel = true;

    public ?bool $deploySuccess = null;

    public function mount(
        Server $server,
        ServerManager $serverManager,
        DoctorInstallHelper $doctor,
        SlaveInstallHelper $slave,
        SlaveDeployProgressService $progress,
    ): void {
        $this->server = $server->load(['nodes', 'latestDiagnosticReport']);
        $serverManager->ensureDoctorSigningKey($this->server);
        $this->server->refresh();

        $this->doctorRemoteCommand = $doctor->remoteCommand($this->server);
        $this->slaveRemoteCommand = $slave->remoteCommand($this->server);
        $this->sshHost = (string) ($this->server->ip_address ?: $this->server->hostname);
        $this->showSshInstallPanel = ! (bool) ($this->server->metadata['agent_installed'] ?? false)
            && $this->server->status->value !== 'online';
        $this->resumeDeployIfRunning($progress);
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

    public function regenerateAgentToken(ServerManager $serverManager): void
    {
        $this->authorizePermission('servers.manage');

        try {
            $serverManager->regenerateAgentToken($this->server);
            $this->server->refresh();
            $this->slaveRemoteCommand = app(SlaveInstallHelper::class)->remoteCommand($this->server);
            $this->doctorRemoteCommand = app(DoctorInstallHelper::class)->remoteCommand($this->server);
            $this->dispatch('notify', type: 'success', message: 'Token agent régénéré.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function ping(ServerManager $serverManager): void
    {
        $online = $serverManager->ping($this->server);
        $this->server->refresh();

        if ($online) {
            $this->dispatch('notify', type: 'success', message: 'Agent seedbox en ligne.');
        } elseif ($this->server->status->value === 'pending') {
            $this->dispatch('notify', type: 'warning', message: 'Agent non installé ou injoignable — utilisez l\'installation SSH ci-dessous.');
        } else {
            $this->dispatch('notify', type: 'danger', message: 'Agent injoignable (vérifiez le service obiora-agent sur le VPS).');
        }
    }

    public function testSshConnection(
        ServerSshKeyService $sshKeys,
        SlaveRemoteDeployService $deploy,
        RemoteOsDetector $osDetector,
    ): void {
        $this->authorizePermission('servers.manage');
        $this->validateSshForm();

        $ssh = $this->resolveConnection($sshKeys);

        if ($ssh === null) {
            $this->sshTestOk = false;
            $this->sshTestResult = 'Saisissez le mot de passe SSH root pour tester la connexion.';

            return;
        }

        $result = $deploy->testConnection($ssh);
        $this->sshTestOk = $result['success'];
        $this->sshTestResult = $result['success']
            ? ($result['message'] ?: 'Connexion réussie.')
            : ($result['message'] ?: 'Connexion SSH refusée.');

        if ($result['success'] && $ssh !== null) {
            $os = $osDetector->detect($ssh);
            if ($os !== null) {
                $this->server->update([
                    'os_name' => $os['name'],
                    'os_version' => $os['version'],
                    'hostname' => $this->server->hostname ?: ($result['message'] ?? $this->server->hostname),
                ]);
                $this->server->refresh();
                $this->sshTestResult .= ' — OS : '.$os['name'].($os['version'] ? ' '.$os['version'] : '');
            }
        }

        $this->deployError = null;
    }

    public function deploySlaveAgent(
        SlaveDeployProgressService $progress,
        SlaveDeployRunner $runner,
        RemoteDeployLauncher $launcher,
    ): void {
        $this->authorizePermission('servers.manage');
        $this->validateSshForm();

        if (! $this->sshTestOk) {
            $this->deployError = 'Testez d\'abord la connexion SSH.';
            $this->dispatch('notify', type: 'warning', message: $this->deployError);

            return;
        }

        if ($this->deployRunning) {
            return;
        }

        try {
            $runner->storeBootstrapPassword($this->server->id, $this->sshPassword);
            $progress->start($this->server->id);
            $progress->appendLog($this->server->id, 'Lancement du processus d\'installation…');
            $launcher->launchSlave($this->server->id, $this->sshHost, $this->sshPort, $this->sshUser);

            $this->deployRunning = true;
            $this->deployFinished = false;
            $this->deployDismissed = false;
            $this->showSshInstallPanel = true;
            $this->deployProgress = 5;
            $this->deployProgressMessage = 'Connexion au serveur distant, installation de l\'agent seedbox…';
            $this->deployError = null;
            $this->deployConsole = ['['.now()->format('H:i:s').'] Installation démarrée…'];
            $this->sshPassword = '';
        } catch (\Throwable $e) {
            $progress->cancel($this->server->id, 'Échec au lancement : '.$e->getMessage());
            $this->deployError = $e->getMessage();
            $this->deployRunning = false;
            $this->dispatch('notify', type: 'danger', message: $this->deployError);
        }
    }

    public function pollDeploy(SlaveDeployProgressService $progress, ServerManager $serverManager): void
    {
        $status = $progress->status($this->server->id);

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

        if ($this->deployRunning && $progress->isStale($this->server->id)) {
            $progress->cancel($this->server->id, 'Installation interrompue (processus bloqué). Relancez.');
            $this->deployRunning = false;
            $this->deployError = 'Installation bloquée — consultez le journal panel ou storage/logs/deploy.log.';
        }

        if (! $this->deployRunning && array_key_exists('success', $status)) {
            $ok = (bool) $status['success'];
            $this->deployFinished = true;
            $this->deploySuccess = $ok;
            $this->server->refresh();
            $this->slaveRemoteCommand = app(SlaveInstallHelper::class)->remoteCommand($this->server);
            $this->doctorRemoteCommand = app(DoctorInstallHelper::class)->remoteCommand($this->server);

            if ($ok) {
                $serverManager->ping($this->server);
                $this->server->refresh();
                $progress->clear($this->server->id);
                $this->deployDismissed = true;
                $this->showSshInstallPanel = false;
                $this->deployConsole = [];
            } else {
                $this->deployError = (string) ($status['message'] ?? 'Échec installation.');
            }

            $this->dispatch('notify', type: $ok ? 'success' : 'danger', message: (string) ($status['message'] ?? ''));
        }
    }

    public function dismissDeployResult(SlaveDeployProgressService $progress): void
    {
        $this->deployDismissed = true;
        $this->deployFinished = false;
        $this->deployConsole = [];
        $this->deployError = null;
        $progress->clear($this->server->id);
    }

    public function toggleSshInstallPanel(): void
    {
        $this->showSshInstallPanel = ! $this->showSshInstallPanel;
    }

    public function useServer(ServerManager $serverManager): void
    {
        $serverManager->setCurrentServer($this->server);
        $this->dispatch('server-changed', serverId: $this->server->id);
        $this->dispatch('notify', type: 'success', message: "Serveur actif : {$this->server->name}");
    }

    public function render()
    {
        return view('servers::livewire.server-show', [
            'agentInstalled' => (bool) ($this->server->metadata['agent_installed'] ?? false)
                || $this->server->status->value === 'online',
            'agentFlags' => app(\App\Support\ServerAgentStatus::class)->flags($this->server),
            'canManage' => auth()->user()?->can('servers.manage') ?? false,
            'awaitingAgent' => $this->server->status->value === 'pending'
                && ! (bool) ($this->server->metadata['agent_installed'] ?? false),
        ]);
    }

    private function resumeDeployIfRunning(SlaveDeployProgressService $progress): void
    {
        $status = $progress->status($this->server->id);

        if (is_array($status) && ($status['running'] ?? false)) {
            $this->deployRunning = true;
            $this->deployDismissed = false;
            $this->showSshInstallPanel = true;
            $this->deployProgress = (int) ($status['progress'] ?? 0);
            $this->deployProgressMessage = (string) ($status['message'] ?? '');
            $this->deployConsole = is_array($status['console'] ?? null) ? $status['console'] : [];

            return;
        }

        $this->deployRunning = false;
        $this->deployFinished = false;
        $this->deployDismissed = true;
    }

    private function resetDeployState(): void
    {
        $this->deployRunning = false;
        $this->deployProgress = 0;
        $this->deployProgressMessage = '';
        $this->deployConsole = [];
        $this->deployFinished = false;
        $this->deploySuccess = null;
    }

    private function resetSshTest(): void
    {
        $this->sshTestOk = false;
        $this->sshTestResult = null;
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
        if ($sshKeys->isInstalledOnRemote($this->server) && $sshKeys->hasKey($this->server)) {
            return $sshKeys->connection($this->server, $this->sshHost, $this->sshPort, $this->sshUser);
        }

        if ($this->sshPassword !== '') {
            return new SshConnection(
                host: $this->sshHost,
                port: $this->sshPort,
                username: $this->sshUser,
                password: $this->sshPassword,
            );
        }

        return null;
    }
}
