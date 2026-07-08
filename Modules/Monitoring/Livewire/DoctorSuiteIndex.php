<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\DTOs\SshConnection;
use App\Jobs\Diagnostics\DoctorRemoteDeployJob;
use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\Diagnostics\DoctorDeployProgressService;
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

    /** Mot de passe ponctuel — jamais persisté */
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
        $this->sshBootstrapResult = null;
        $this->resumeDeployIfRunning();
    }

    public function generateSshKey(ServerSshKeyService $sshKeys): void
    {
        $this->authorizeDeploy();
        $server = Server::query()->findOrFail($this->serverId);

        try {
            $this->sshPublicKey = $sshKeys->generate($server);
            $this->sshKeyInstalled = false;
            $this->sshBootstrapResult = 'Clé SSH générée. Installez-la sur le serveur distant (mot de passe une seule fois).';
            $this->dispatch('notify', type: 'success', message: 'Clé SSH dédiée générée.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        }
    }

    public function bootstrapSshKey(ServerSshKeyService $sshKeys, DoctorRemoteDeployService $deploy): void
    {
        $this->authorizeDeploy();
        $this->validateSshForm();

        if ($this->sshPassword === '') {
            $this->addError('sshPassword', 'Mot de passe requis pour installer la clé (une seule fois).');

            return;
        }

        $server = Server::query()->findOrFail($this->serverId);

        if (! $sshKeys->hasKey($server)) {
            $this->dispatch('notify', type: 'warning', message: 'Générez d\'abord une clé SSH dédiée.');

            return;
        }

        $result = $sshKeys->installPublicKeyOnRemote(
            $server,
            $this->bootstrapConnection(),
            $deploy,
        );

        $this->sshPassword = '';
        $this->sshBootstrapResult = $result['output'];
        $this->refreshSshState($server->fresh(), $sshKeys);

        $this->dispatch(
            'notify',
            type: $result['success'] ? 'success' : 'danger',
            message: $result['success'] ? 'Clé installée sur le serveur distant.' : 'Échec installation clé.',
        );
    }

    public function testSshConnection(ServerSshKeyService $sshKeys, DoctorRemoteDeployService $deploy): void
    {
        $this->authorizeDeploy();
        $this->validateSshForm();

        $server = Server::query()->findOrFail($this->serverId);
        $ssh = $this->resolveConnection($server, $sshKeys);

        if ($ssh === null) {
            $this->sshTestOk = false;
            $this->sshTestResult = 'Générez une clé SSH ou saisissez un mot de passe pour tester.';

            return;
        }

        $result = $deploy->testConnection($ssh);
        $this->sshTestOk = $result['success'];
        $this->sshTestResult = $result['message']."\n".$result['output'];
        $this->sshPassword = '';
    }

    public function deployRemote(DoctorDeployProgressService $progress): void
    {
        $this->authorizeDeploy();
        $this->validateSshForm();

        $server = Server::query()->findOrFail($this->serverId);

        if (! app(ServerSshKeyService::class)->isInstalledOnRemote($server)) {
            $this->deployError = 'Installez d\'abord la clé SSH dédiée sur le serveur distant.';
            $this->dispatch('notify', type: 'warning', message: $this->deployError);

            return;
        }

        $progress->start($server->id);

        DoctorRemoteDeployJob::dispatch(
            $server->id,
            $this->sshHost,
            $this->sshPort,
            $this->sshUser,
            $this->deployDoctor,
            $this->deployCrashAnalyzer,
        );

        $this->deployRunning = true;
        $this->deployProgress = 5;
        $this->deployProgressMessage = 'Déploiement démarré en arrière-plan…';
        $this->deployError = null;
        $this->deploySteps = [];
        $this->sshPassword = '';
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
        $this->deployRunning = (bool) ($status['running'] ?? false);

        if (! $this->deployRunning && array_key_exists('success', $status)) {
            $ok = (bool) $status['success'];
            if (! $ok) {
                $this->deployError = (string) ($status['message'] ?? 'Échec du déploiement.');
            }
            $this->dispatch('notify', type: $ok ? 'success' : 'danger', message: (string) ($status['message'] ?? ''));
            $this->resetDeployState(false);
        }
    }

    public function render(DoctorSuiteService $suite)
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
        if ($sshKeys->hasKey($server)) {
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
