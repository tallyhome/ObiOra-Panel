<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\DTOs\SshConnection;
use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Deploy\DeployLogService;
use App\Services\Deploy\RemoteDeployLauncher;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\DoctorDeployRunner;
use App\Services\Diagnostics\DoctorRemoteDeployService;
use App\Services\Diagnostics\DoctorSuiteService;
use App\Services\Diagnostics\ServerSshKeyService;
use App\Support\DoctorInstallHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Doctor & Suite')]
final class DoctorSuiteIndex extends Component
{
    public ?int $serverId = null;

    public string $localInstall = '';

    public string $remoteInstall = '';

    public string $remoteSuiteInstall = '';

    public string $sshHost = '';

    public int $sshPort = 22;

    public string $sshUser = 'root';

    /** Mot de passe ponctuel — jamais persisté en base */
    public string $sshPassword = '';

    public bool $deployDoctor = true;

    public bool $deployCrashAnalyzer = true;

    public bool $deployRunning = false;

    public int $deployProgress = 0;

    public string $deployProgressMessage = '';

    /** @var list<array{component: string, success: bool, output: string}> */
    public array $deploySteps = [];

    public ?string $deployError = null;

    public ?string $sshTestResult = null;

    public bool $sshTestOk = false;

    public ?string $sshBootstrapResult = null;

    public ?string $sshPublicKey = null;

    public bool $sshKeyInstalled = false;

    /** @var list<string> */
    public array $deployConsole = [];

    public bool $deployFinished = false;

    public ?bool $deploySuccess = null;

    public function mount(ServerManager $servers, DoctorInstallHelper $doctor, ServerSshKeyService $sshKeys): void
    {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $current = $servers->getCurrentServer();
        $this->serverId = $current?->id;
        $this->refreshInstallCommands($doctor, $current);
        $this->refreshSshState($current, $sshKeys);
        $this->sshHost = $current?->ip_address ?? '';
        $this->resumeDeployIfRunning();
    }

    public function updatedServerId(DoctorInstallHelper $doctor, ServerSshKeyService $sshKeys): void
    {
        $server = Server::query()->find($this->serverId);
        $this->refreshInstallCommands($doctor, $server);
        $this->refreshSshState($server, $sshKeys);
        if ($server !== null) {
            $this->sshHost = $server->ip_address;
        }
        $this->sshTestResult = null;
        $this->sshTestOk = false;
        $this->sshBootstrapResult = null;
        $this->deployError = null;
        $this->resumeDeployIfRunning();
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

    public function testSshConnection(ServerSshKeyService $sshKeys, DoctorRemoteDeployService $deploy): void
    {
        $this->authorizeDeploy();
        $this->validateSshForm();

        $server = Server::query()->findOrFail($this->serverId);
        $ssh = $this->resolveConnection($server, $sshKeys);

        if ($ssh === null) {
            $this->sshTestOk = false;
            $this->sshTestResult = 'Saisissez le mot de passe SSH du serveur distant pour tester la connexion.';

            return;
        }

        $result = $deploy->testConnection($ssh);
        $this->sshTestOk = $result['success'];

        if ($result['success']) {
            $hostname = trim(str_replace(['OBIORA_SSH_OK', "\n"], ['', ' '], $result['output']));
            $this->sshTestResult = 'Connexion réussie'.($hostname !== '' ? ' — '.$hostname : '.').' Vous pouvez lancer l\'installation.';
        } else {
            $this->sshTestResult = $result['message'] ?: ($result['output'] ?: 'Connexion SSH refusée ou timeout.');
        }

        $this->deployError = null;
    }

    public function deployRemote(
        DoctorDeployProgressService $progress,
        DoctorDeployRunner $runner,
        RemoteDeployLauncher $launcher,
    ): void {
        $this->authorizeDeploy();
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
            $server = Server::query()->findOrFail($this->serverId);

            $runner->storeBootstrapPassword($server->id, $this->sshPassword);

            $progress->start($server->id);
            $progress->appendLog($server->id, 'Lancement du processus d\'installation…');

            $launcher->launchDoctor(
                $server->id,
                $this->sshHost,
                $this->sshPort,
                $this->sshUser,
                $this->deployDoctor,
                $this->deployCrashAnalyzer,
            );

            $this->deployRunning = true;
            $this->deployFinished = false;
            $this->deploySuccess = null;
            $this->deployProgress = 5;
            $this->deployProgressMessage = 'Connexion au serveur distant, envoi des agents…';
            $this->deployError = null;
            $this->deploySteps = [];
            $this->deployConsole = ['['.now()->format('H:i:s').'] Déploiement démarré…'];
            $this->sshPassword = '';

            $this->dispatch('deploy-console-scroll');
        } catch (\Throwable $e) {
            $progress->cancel($this->serverId, 'Échec au lancement : '.$e->getMessage());
            $this->deployError = $e->getMessage();
            $this->deployRunning = false;
            $this->dispatch('notify', type: 'danger', message: $this->deployError);
        }
    }

    public function cancelDeploy(DoctorDeployProgressService $progress): void
    {
        if ($this->serverId === null) {
            return;
        }

        $progress->cancel($this->serverId, 'Déploiement annulé depuis le panel.');
        $this->resetDeployState(false);
        $this->deployError = 'Déploiement annulé.';
    }

    public function pollDeploy(DoctorDeployProgressService $progress): void
    {
        if ($this->serverId === null) {
            $this->resetDeployState();

            return;
        }

        $status = $progress->status($this->serverId);

        if (! is_array($status)) {
            if ($this->deployRunning) {
                $this->resetDeployState();
            }

            return;
        }

        $this->deployProgress = (int) ($status['progress'] ?? 0);
        $this->deployProgressMessage = (string) ($status['message'] ?? '');
        $this->deploySteps = is_array($status['steps'] ?? null) ? $status['steps'] : [];
        $this->deployConsole = is_array($status['console'] ?? null) ? $status['console'] : [];
        $this->deployRunning = (bool) ($status['running'] ?? false);

        if ($this->deployRunning && $progress->isStale($this->serverId)) {
            $progress->cancel(
                $this->serverId,
                'Déploiement interrompu (processus panel arrêté ou bloqué). Relancez l\'installation.',
            );
            $this->deployRunning = false;
            $this->deployError = 'Le déploiement semble bloqué. Consultez le journal panel ci-dessous ou storage/logs/deploy.log.';
        }

        if (! $this->deployRunning && array_key_exists('success', $status)) {
            $ok = (bool) $status['success'];
            $this->deployFinished = true;
            $this->deploySuccess = $ok;
            if (! $ok) {
                $this->deployError = (string) ($status['message'] ?? 'Échec du déploiement.');
            }
            $this->refreshSshState(
                Server::query()->find($this->serverId),
                app(ServerSshKeyService::class),
            );
            $this->dispatch('notify', type: $ok ? 'success' : 'danger', message: (string) ($status['message'] ?? ''));
            $this->dispatch('deploy-console-scroll');
        }
    }

    public function render(DoctorSuiteService $suite, DeployLogService $deployLog)
    {
        $servers = Server::query()
            ->with('latestDiagnosticReport')
            ->orderByDesc('is_master')
            ->orderBy('name')
            ->get();

        $server = $this->serverId !== null
            ? $servers->firstWhere('id', $this->serverId)
            : $servers->first();

        $overview = $server !== null ? $suite->serverOverview($server) : null;
        $fleet = $suite->fleetOverview($servers);

        $reportCount = DiagnosticReport::query()->count();
        $lastReportAt = DiagnosticReport::query()->max('generated_at');
        $lastReportLabel = $lastReportAt
            ? \Illuminate\Support\Carbon::parse($lastReportAt)->format('d/m/Y H:i')
            : null;

        return view('monitoring::livewire.doctor-suite-index', [
            'server' => $server,
            'overview' => $overview,
            'doctorFleet' => $servers,
            'fleetOverview' => $fleet,
            'reportCount' => $reportCount,
            'lastReportLabel' => $lastReportLabel,
            'suiteUrl' => (string) config('obiora.suite.url', ''),
            'canManageServers' => auth()->user()?->can('servers.manage') ?? false,
            'panelDeployLogs' => $this->serverId !== null
                ? $deployLog->recentForServer($this->serverId, 'doctor')
                : collect(),
        ]);
    }

    private function resumeDeployIfRunning(): void
    {
        if ($this->serverId === null) {
            return;
        }

        $status = app(DoctorDeployProgressService::class)->status($this->serverId);

        if (is_array($status) && ($status['running'] ?? false)) {
            $this->deployRunning = true;
            $this->deployProgress = (int) ($status['progress'] ?? 0);
            $this->deployProgressMessage = (string) ($status['message'] ?? '');
            $this->deploySteps = is_array($status['steps'] ?? null) ? $status['steps'] : [];
            $this->deployConsole = is_array($status['console'] ?? null) ? $status['console'] : [];

            return;
        }

        if (is_array($status) && array_key_exists('success', $status)) {
            $this->deployRunning = false;
            $this->deployFinished = true;
            $this->deploySuccess = (bool) $status['success'];
            $this->deployProgress = (int) ($status['progress'] ?? 100);
            $this->deployProgressMessage = (string) ($status['message'] ?? '');
            $this->deploySteps = is_array($status['steps'] ?? null) ? $status['steps'] : [];
            $this->deployConsole = is_array($status['console'] ?? null) ? $status['console'] : [];
        }
    }

    private function resetDeployState(bool $clearCache = true): void
    {
        if ($clearCache && $this->serverId !== null) {
            app(DoctorDeployProgressService::class)->clear($this->serverId);
        }

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

    private function refreshInstallCommands(DoctorInstallHelper $doctor, ?Server $server): void
    {
        $this->localInstall = $doctor->localCommand($server);
        $this->remoteInstall = $doctor->remoteCommand($server);
        $this->remoteSuiteInstall = $doctor->remoteSuiteCommand($server);
    }

    private function refreshSshState(?Server $server, ServerSshKeyService $sshKeys): void
    {
        if ($server === null) {
            $this->sshPublicKey = null;
            $this->sshKeyInstalled = false;

            return;
        }

        $this->sshPublicKey = $sshKeys->publicKey($server);
        $this->sshKeyInstalled = $sshKeys->isInstalledOnRemote($server);
    }

    private function validateSshForm(): void
    {
        $this->validate([
            'sshHost' => ['required', 'string', 'max:255'],
            'sshPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'sshUser' => ['required', 'string', 'max:64'],
        ]);
    }

    private function bootstrapConnection(): SshConnection
    {
        return new SshConnection(
            host: $this->sshHost,
            port: $this->sshPort,
            username: $this->sshUser,
            password: $this->sshPassword !== '' ? $this->sshPassword : null,
        );
    }

    private function resolveConnection(Server $server, ServerSshKeyService $sshKeys): ?SshConnection
    {
        if ($sshKeys->isInstalledOnRemote($server) && $sshKeys->hasKey($server)) {
            return $sshKeys->connection($server, $this->sshHost, $this->sshPort, $this->sshUser);
        }

        if ($this->sshPassword !== '') {
            return $this->bootstrapConnection();
        }

        return null;
    }

    private function authorizeDeploy(): void
    {
        abort_unless(auth()->user()?->can('servers.manage'), 403);
        abort_if($this->serverId === null, 422, 'Sélectionnez un serveur.');
    }
}
