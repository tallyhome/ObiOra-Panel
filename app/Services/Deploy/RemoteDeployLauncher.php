<?php

declare(strict_types=1);

namespace App\Services\Deploy;

use App\Contracts\SystemExecutorInterface;
use App\Jobs\Diagnostics\DoctorRemoteDeployJob;
use App\Jobs\Servers\SlaveRemoteDeployJob;
use App\Services\Core\ObioraQueueService;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Servers\SlaveDeployProgressService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Lance les déploiements distants via obiora-queue (pas depuis PHP-FPM).
 */
final class RemoteDeployLauncher
{
    public function __construct(
        private readonly ObioraQueueService $queue,
        private readonly DoctorDeployProgressService $doctorProgress,
        private readonly SlaveDeployProgressService $slaveProgress,
        private readonly DeployLogService $deployLog,
        private readonly SystemExecutorInterface $executor,
    ) {}

    public function launchDoctor(
        int $serverId,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        bool $installDoctor,
        bool $installCrashAnalyzer,
        bool $installCrashHunter = true,
        bool $installSlave = false,
    ): void {
        $this->deployLog->log($serverId, 'doctor', 'Demande d\'installation Doctor & Suite', 'info', [
            'host' => $sshHost,
            'port' => $sshPort,
            'user' => $sshUser,
            'crash_hunter' => $installCrashHunter,
            'slave' => $installSlave,
        ]);

        $workerOk = $this->queue->ensureWorkerRunning();

        try {
            DoctorRemoteDeployJob::dispatch(
                $serverId,
                $sshHost,
                $sshPort,
                $sshUser,
                $installDoctor,
                $installCrashAnalyzer,
                $installCrashHunter,
                $installSlave,
            );

            $this->doctorProgress->appendLog(
                $serverId,
                $workerOk
                    ? 'Tâche envoyée au worker obiora-queue.'
                    : 'Tâche en file d\'attente — le worker obiora-queue a été relancé, patientez…',
            );

            return;
        } catch (Throwable $exception) {
            Log::warning('Dispatch DoctorRemoteDeployJob échoué — fallback CLI', [
                'message' => $exception->getMessage(),
            ]);
        }

        $this->launchDoctorCli($serverId, $sshHost, $sshPort, $sshUser, $installDoctor, $installCrashAnalyzer, $installCrashHunter, $installSlave);
    }

    /**
     * @param  list<string>  $components
     */
    public function launchAgentUpgrade(
        int $serverId,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        array $components = [],
    ): void {
        $this->deployLog->log($serverId, 'doctor', 'Demande de mise à jour agents', 'info', [
            'host' => $sshHost,
            'components' => $components,
        ]);

        $workerOk = $this->queue->ensureWorkerRunning();

        try {
            DoctorRemoteDeployJob::dispatch(
                $serverId,
                $sshHost,
                $sshPort,
                $sshUser,
                false,
                false,
                false,
                false,
                true,
                $components,
            );

            $this->doctorProgress->appendLog(
                $serverId,
                $workerOk
                    ? 'Tâche de mise à jour envoyée au worker obiora-queue.'
                    : 'Tâche en file — worker relancé, patientez…',
            );

            return;
        } catch (Throwable $exception) {
            Log::warning('Dispatch upgrade job échoué — fallback CLI', [
                'message' => $exception->getMessage(),
            ]);
        }

        $this->launchDoctorCli($serverId, $sshHost, $sshPort, $sshUser, false, false, false, false, true, $components);
    }

    public function launchSlave(
        int $serverId,
        string $sshHost,
        int $sshPort,
        string $sshUser,
    ): void {
        $this->deployLog->log($serverId, 'slave', 'Demande d\'installation agent seedbox', 'info', [
            'host' => $sshHost,
            'port' => $sshPort,
            'user' => $sshUser,
        ]);

        $workerOk = $this->queue->ensureWorkerRunning();

        try {
            SlaveRemoteDeployJob::dispatch($serverId, $sshHost, $sshPort, $sshUser);

            $this->slaveProgress->appendLog(
                $serverId,
                $workerOk
                    ? 'Tâche envoyée au worker obiora-queue.'
                    : 'Tâche en file d\'attente — le worker obiora-queue a été relancé, patientez…',
            );

            return;
        } catch (Throwable $exception) {
            Log::warning('Dispatch SlaveRemoteDeployJob échoué — fallback CLI', [
                'message' => $exception->getMessage(),
            ]);
        }

        $this->launchSlaveCli($serverId, $sshHost, $sshPort, $sshUser);
    }

    private function launchDoctorCli(
        int $serverId,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        bool $installDoctor,
        bool $installCrashAnalyzer,
        bool $installCrashHunter = true,
        bool $installSlave = false,
        bool $upgradeOnly = false,
        array $upgradeComponents = [],
    ): void {
        $logFile = storage_path('logs/deploy-doctor.log');
        $command = $this->buildArtisanCommand([
            'obiora:doctor-deploy',
            (string) $serverId,
            $sshHost,
            (string) $sshPort,
            $sshUser,
            $installDoctor ? 'yes' : 'no',
            $installCrashAnalyzer ? 'yes' : 'no',
            $installCrashHunter ? 'yes' : 'no',
            $installSlave ? 'yes' : 'no',
            $upgradeOnly ? 'yes' : 'no',
            implode(',', $upgradeComponents),
        ], $logFile);

        $this->doctorProgress->appendLog($serverId, 'Fallback : lancement CLI en arrière-plan…');
        $this->executor->run($command, ['timeout' => 15]);
    }

    private function launchSlaveCli(
        int $serverId,
        string $sshHost,
        int $sshPort,
        string $sshUser,
    ): void {
        $logFile = storage_path('logs/deploy-slave.log');
        $command = $this->buildArtisanCommand([
            'obiora:slave-deploy',
            (string) $serverId,
            $sshHost,
            (string) $sshPort,
            $sshUser,
        ], $logFile);

        $this->slaveProgress->appendLog($serverId, 'Fallback : lancement CLI en arrière-plan…');
        $this->executor->run($command, ['timeout' => 15]);
    }

    /**
     * @param  list<string>  $args
     */
    private function buildArtisanCommand(array $args, string $logFile): string
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $artisan = base_path('artisan');
        $quoted = implode(' ', array_map(static fn (string $a): string => escapeshellarg($a), array_merge([$php, $artisan], $args)));

        if (PHP_OS_FAMILY === 'Windows') {
            return 'start /B '.$quoted.' >> '.escapeshellarg($logFile).' 2>&1';
        }

        return 'nohup '.$quoted.' >> '.escapeshellarg($logFile).' 2>&1 &';
    }
}
