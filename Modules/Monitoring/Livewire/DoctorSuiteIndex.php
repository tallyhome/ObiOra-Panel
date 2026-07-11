<?php

declare(strict_types=1);

namespace Modules\Monitoring\Livewire;

use App\DTOs\SshConnection;
use App\Models\DiagnosticReport;
use App\Models\Server;
use App\Services\CrashHunter\CrashHunterMetricsService;
use App\Services\Core\ServerManager;
use App\Services\Diagnostics\DiagnosticsAgentVersionService;
use App\Services\Diagnostics\DoctorDeployTargetResolver;
use App\Services\Deploy\DeployLogService;
use App\Services\Deploy\RemoteDeployLauncher;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\DoctorDeployRunner;
use App\Services\Diagnostics\DoctorRemoteDeployService;
use App\Services\Diagnostics\DoctorSuiteService;
use App\Services\Diagnostics\RemoteAgentControlService;
use App\Services\Diagnostics\LocalDoctorDeployService;
use App\Services\Diagnostics\ServerSshKeyService;
use App\Services\Diagnostics\ServerTimezoneService;
use App\Support\DoctorInstallHelper;
use App\Support\PanelLocalTarget;
use App\Support\TimezoneCatalog;
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

    public bool $deployCrashHunter = true;

    public bool $deploySlave = false;

    /** @var list<array<string, mixed>> */
    public array $runningAgents = [];

    public bool $agentsLoading = false;

    public ?string $agentControlMessage = null;

    public bool $agentControlOk = false;

    /** @var array<string, mixed>|null */
    public ?array $selectedSnapshot = null;

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

    public bool $deployDismissed = true;

    public ?bool $deploySuccess = null;

    public ?string $sshRemoteHostname = null;

    public string $selectedTimezone = 'Europe/Paris';

    public ?string $serverTimezone = null;

    public ?string $serverDateTime = null;

    public ?string $serverNtp = null;

    public ?string $timezoneMessage = null;

    public bool $timezoneLoading = false;

    public function mount(
        ServerManager $servers,
        DoctorInstallHelper $doctor,
        ServerSshKeyService $sshKeys,
        ServerTimezoneService $timezone,
    ): void {
        abort_unless(auth()->user()?->can('modules.view'), 403);

        $current = $servers->getCurrentServer();
        $this->serverId = $current?->id;
        $this->refreshInstallCommands($doctor, $current);
        $this->refreshSshState($current, $sshKeys);
        $this->sshHost = $current?->ip_address ?? '';
        $this->refreshServerTimezone($timezone);
        $this->resumeDeployIfRunning();
    }

    public function updatedServerId(
        DoctorInstallHelper $doctor,
        ServerSshKeyService $sshKeys,
        ServerTimezoneService $timezone,
    ): void {
        $server = Server::query()->find($this->serverId);
        $this->refreshInstallCommands($doctor, $server);
        $this->refreshSshState($server, $sshKeys);
        if ($server !== null) {
            $this->sshHost = $server->ip_address;
        }
        $this->sshTestOk = false;
        $this->sshBootstrapResult = null;
        $this->deployError = null;
        $this->timezoneMessage = null;
        $this->refreshServerTimezone($timezone);
        $this->resumeDeployIfRunning();
    }

    public function updatedSshHost(DoctorDeployTargetResolver $targetResolver): void
    {
        $this->resetSshTest();
        $match = $targetResolver->findByIp($this->sshHost);
        if ($match !== null) {
            $this->serverId = $match->id;
        }
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
        DoctorRemoteDeployService $deploy,
        LocalDoctorDeployService $localDeploy,
    ): void {
        $this->authorizeDeploy();
        $this->validateSshForm();

        $server = Server::query()->findOrFail($this->serverId);

        if (PanelLocalTarget::isPanelServer($server, $this->sshHost)) {
            $result = $localDeploy->testLocal();
            $this->sshTestOk = $result['success'];
            $hostname = trim(str_replace(['OBIORA_SSH_OK', "\n"], ['', ' '], $result['output']));
            $this->sshRemoteHostname = $hostname !== '' ? $hostname : null;
            $this->sshTestResult = $result['success']
                ? 'Serveur local du panel — prêt pour installation directe.'
                    .($hostname !== '' ? ' ('.$hostname.')' : '')
                : ($result['message'] ?: 'Environnement local indisponible.');
            $this->deployError = null;

            return;
        }

        $ssh = $this->resolveConnection($server, $sshKeys);

        if ($ssh === null) {
            $this->sshTestOk = false;
            $this->sshTestResult = $this->sshPassword === ''
                ? 'Saisissez le mot de passe SSH du serveur distant pour tester la connexion.'
                : 'Connexion SSH impossible — vérifiez IP, port, utilisateur et mot de passe.';

            return;
        }

        $result = $deploy->testConnection($ssh);

        if (
            ! $result['success']
            && $ssh->privateKey !== null
            && $this->sshPassword !== ''
        ) {
            $result = $deploy->testConnection($this->bootstrapConnection());
        }

        $this->sshTestOk = $result['success'];

        if ($result['success']) {
            $hostname = trim(str_replace(['OBIORA_SSH_OK', "\n"], ['', ' '], $result['output']));
            $this->sshRemoteHostname = $hostname !== '' ? $hostname : null;
            $this->sshTestResult = 'Connexion réussie'.($hostname !== '' ? ' — '.$hostname : '.').' Vous pouvez lancer l\'installation.';

            $match = app(DoctorDeployTargetResolver::class)->findByIp($this->sshHost);
            if ($match !== null) {
                $this->serverId = $match->id;
            }
        } else {
            $this->sshRemoteHostname = null;
            $this->sshTestResult = $result['message'] ?: ($result['output'] ?: 'Connexion SSH refusée ou timeout.');
        }

        $this->deployError = null;
        $this->refreshServerTimezone(app(ServerTimezoneService::class), $sshKeys);
    }

    public function refreshServerTimezone(ServerTimezoneService $timezone, ServerSshKeyService $sshKeys): void
    {
        if ($this->serverId === null) {
            $this->serverTimezone = null;
            $this->serverDateTime = null;
            $this->serverNtp = null;

            return;
        }

        $server = Server::query()->find($this->serverId);

        if ($server === null) {
            return;
        }

        $this->timezoneLoading = true;

        try {
            $host = $this->resolveTimezoneHost($server);
            $connection = $this->resolveAgentConnection($server, $sshKeys);
            $status = $timezone->status($server, $connection, $host);
            $this->serverTimezone = $status['timezone'];
            $this->serverDateTime = $status['datetime'];
            $this->serverNtp = $status['ntp'];

            if ($status['timezone'] !== null && TimezoneCatalog::isValid($status['timezone'])) {
                $this->selectedTimezone = $status['timezone'];
            }

            if (($status['timezone'] ?? null) === null && ($status['error'] ?? null) !== null) {
                $this->timezoneMessage = $status['error'];
            }
        } finally {
            $this->timezoneLoading = false;
        }
    }

    public function applyServerTimezone(ServerTimezoneService $timezone, ServerSshKeyService $sshKeys): void
    {
        $this->authorizeDeploy();
        abort_unless(TimezoneCatalog::isValid($this->selectedTimezone), 422, 'Fuseau horaire invalide.');

        $server = Server::query()->findOrFail($this->serverId);
        $this->timezoneLoading = true;
        $this->timezoneMessage = null;

        try {
            $host = $this->resolveTimezoneHost($server);
            $connection = $this->resolveAgentConnection($server, $sshKeys);
            $result = $timezone->apply($server, $this->selectedTimezone, $connection, $host);
            $this->serverTimezone = $result['status']['timezone'];
            $this->serverDateTime = $result['status']['datetime'];
            $this->serverNtp = $result['status']['ntp'];
            $this->timezoneMessage = $result['message'];

            $this->dispatch(
                'notify',
                type: $result['success'] ? 'success' : 'danger',
                message: $result['message'],
            );
        } catch (\Throwable $e) {
            $this->timezoneMessage = $e->getMessage();
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        } finally {
            $this->timezoneLoading = false;
        }
    }

    public function deployRemote(
        DoctorDeployProgressService $progress,
        DoctorDeployRunner $runner,
        RemoteDeployLauncher $launcher,
        DoctorDeployTargetResolver $targetResolver,
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
            $server = $targetResolver->resolve(
                $this->sshHost,
                $this->sshRemoteHostname,
                $this->serverId,
            );
            $this->serverId = $server->id;

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
                $this->deployCrashHunter,
                $this->deploySlave,
            );

            $this->deployRunning = true;
            $this->deployFinished = false;
            $this->deployDismissed = false;
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

    public function upgradeAgents(
        DoctorDeployProgressService $progress,
        RemoteDeployLauncher $launcher,
        DiagnosticsAgentVersionService $versions,
        DoctorDeployTargetResolver $targetResolver,
    ): void {
        $this->authorizeDeploy();
        $this->validateSshForm();

        if (! $this->sshTestOk) {
            $this->dispatch('notify', type: 'warning', message: 'Testez d\'abord la connexion SSH.');

            return;
        }

        if ($this->deployRunning) {
            return;
        }

        try {
            $server = $targetResolver->resolve(
                $this->sshHost,
                $this->sshRemoteHostname,
                $this->serverId,
            );
            $this->serverId = $server->id;

            $components = $versions->outdatedComponents($server);
            if ($components === []) {
                $this->dispatch('notify', type: 'info', message: 'Les agents distants sont déjà à jour.');

                return;
            }

            app(DoctorDeployRunner::class)->storeBootstrapPassword($server->id, $this->sshPassword);

            $progress->start($server->id);
            $progress->appendLog($server->id, 'Mise à jour agents : '.implode(', ', $components));

            $launcher->launchAgentUpgrade(
                $server->id,
                $this->sshHost,
                $this->sshPort,
                $this->sshUser,
                $components,
            );

            $this->deployRunning = true;
            $this->deployFinished = false;
            $this->deployDismissed = false;
            $this->deploySuccess = null;
            $this->deployProgress = 5;
            $this->deployProgressMessage = 'Mise à jour des agents distants…';
            $this->deployError = null;
            $this->deploySteps = [];
            $this->deployConsole = ['['.now()->format('H:i:s').'] Mise à jour agents démarrée…'];
            $this->sshPassword = '';

            $this->dispatch('deploy-console-scroll');
        } catch (\Throwable $e) {
            $progress->cancel($this->serverId, 'Échec au lancement : '.$e->getMessage());
            $this->deployError = $e->getMessage();
            $this->deployRunning = false;
            $this->dispatch('notify', type: 'danger', message: $this->deployError);
        }
    }

    public function dismissDeployResult(DoctorDeployProgressService $progress): void
    {
        $this->deployDismissed = true;
        $this->deployFinished = false;
        $this->deployConsole = [];
        $this->deploySteps = [];
        $this->deployError = null;

        if ($this->serverId !== null) {
            $progress->clear($this->serverId);
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
            $this->deployDismissed = false;
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

    public function refreshRunningAgents(RemoteAgentControlService $control): void
    {
        $this->authorizeDeploy();
        if (! $this->sshTestOk) {
            $this->agentControlMessage = 'Testez d\'abord la connexion SSH.';
            $this->agentControlOk = false;

            return;
        }

        $this->agentsLoading = true;
        $this->agentControlMessage = null;

        try {
            $server = Server::query()->findOrFail($this->serverId);
            $connection = $this->resolveAgentConnection($server, app(ServerSshKeyService::class));
            $result = $control->listAgents($server, $this->sshHost, $this->sshPort, $this->sshUser, $connection);
            $this->runningAgents = $result['services'];
            $this->agentControlOk = $result['success'];
            $this->agentControlMessage = $result['message'];
        } catch (\Throwable $e) {
            $this->runningAgents = [];
            $this->agentControlOk = false;
            $this->agentControlMessage = $e->getMessage();
        } finally {
            $this->agentsLoading = false;
        }
    }

    public function stopAllAgents(RemoteAgentControlService $control): void
    {
        $this->authorizeDeploy();
        if (! $this->sshTestOk) {
            $this->dispatch('notify', type: 'warning', message: 'Testez d\'abord la connexion SSH.');

            return;
        }

        $this->agentsLoading = true;

        try {
            $server = Server::query()->findOrFail($this->serverId);
            $connection = $this->resolveAgentConnection($server, app(ServerSshKeyService::class));
            $result = $control->stopAllDiagnostics($server, $this->sshHost, $this->sshPort, $this->sshUser, $connection);
            $this->agentControlOk = $result['success'];
            $this->agentControlMessage = $result['message'];
            $this->runningAgents = [];
            $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['message']);
        } catch (\Throwable $e) {
            $this->agentControlOk = false;
            $this->agentControlMessage = $e->getMessage();
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        } finally {
            $this->agentsLoading = false;
        }
    }

    public function purgeAllAgents(RemoteAgentControlService $control): void
    {
        $this->authorizeDeploy();
        if (! $this->sshTestOk) {
            $this->dispatch('notify', type: 'warning', message: 'Testez d\'abord la connexion SSH.');

            return;
        }

        $this->agentsLoading = true;

        try {
            $server = Server::query()->findOrFail($this->serverId);
            $connection = $this->resolveAgentConnection($server, app(ServerSshKeyService::class));
            $result = $control->purgeAllDiagnostics(
                $server,
                $this->sshHost,
                $this->sshPort,
                $this->sshUser,
                $connection,
                true,
            );
            $this->agentControlOk = $result['success'];
            $this->agentControlMessage = $result['message'];
            $this->runningAgents = [];
            $this->sshKeyInstalled = false;
            $this->refreshSshState($server, app(ServerSshKeyService::class));
            $this->dispatch('notify', type: $result['success'] ? 'success' : 'danger', message: $result['message']);
        } catch (\Throwable $e) {
            $this->agentControlOk = false;
            $this->agentControlMessage = $e->getMessage();
            $this->dispatch('notify', type: 'danger', message: $e->getMessage());
        } finally {
            $this->agentsLoading = false;
        }
    }

    public function closeSnapshot(): void
    {
        $this->selectedSnapshot = null;
    }

    public function inspectSnapshot(int $snapshotId, CrashHunterMetricsService $metrics): void
    {
        if ($this->serverId === null) {
            return;
        }

        $server = Server::query()->findOrFail($this->serverId);
        $detail = $metrics->snapshotDetail($server, $snapshotId);

        if ($detail === null) {
            $this->dispatch('notify', type: 'warning', message: 'Snapshot introuvable.');

            return;
        }

        $this->selectedSnapshot = $detail;
    }

    private function resolveTimezoneHost(Server $server): string
    {
        $host = trim($this->sshHost);

        if ($host !== '') {
            return $host;
        }

        $meta = $server->metadata ?? [];

        return trim((string) ($meta['doctor_deploy']['remote_host'] ?? $server->ip_address));
    }

    private function resolveAgentConnection(Server $server, ServerSshKeyService $sshKeys): ?SshConnection
    {
        $connection = $this->resolveConnection($server, $sshKeys);

        if ($connection === null && $this->sshPassword !== '') {
            return $this->bootstrapConnection();
        }

        return $connection;
    }

    public function render(DoctorSuiteService $suite, DeployLogService $deployLog, DiagnosticsAgentVersionService $agentVersions)
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
            'timezoneChoices' => TimezoneCatalog::choices(),
            'agentVersionRows' => $server !== null ? $agentVersions->compare($server) : [],
            'agentUpgradeNeeded' => $server !== null && $agentVersions->needsUpgrade($server),
            'bundledAgentVersions' => $agentVersions->bundledVersions(),
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
            $this->deployDismissed = false;
            $this->deployProgress = (int) ($status['progress'] ?? 0);
            $this->deployProgressMessage = (string) ($status['message'] ?? '');
            $this->deploySteps = is_array($status['steps'] ?? null) ? $status['steps'] : [];
            $this->deployConsole = is_array($status['console'] ?? null) ? $status['console'] : [];

            return;
        }

        $this->deployRunning = false;
        $this->deployFinished = false;
        $this->deployDismissed = true;
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
        $this->sshRemoteHostname = null;
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
        if ($sshKeys->keyAppliesToHost($server, $this->sshHost)) {
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
