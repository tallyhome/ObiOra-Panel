<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Support\DoctorInstallHelper;

/**
 * Déploie Doctor et Crash Analyzer via SSH.
 * Après bootstrap, seule la clé SSH dédiée ou le jeton agent API est utilisé.
 */
final class DoctorRemoteDeployService
{
    public function __construct(
        private readonly DoctorInstallHelper $doctor,
        private readonly SshRemoteExecutor $ssh,
    ) {}

    /**
     * @param  callable(int, string, list<array{component: string, success: bool, output: string}>): void|null  $onProgress
     * @return array{success: bool, steps: list<array{component: string, success: bool, output: string}>}
     */
    public function deploySuite(
        Server $server,
        SshConnection $connection,
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        ?callable $onProgress = null,
    ): array {
        $steps = [];
        $panelUrl = rtrim((string) config('app.url'), '/');

        if ($onProgress !== null) {
            $onProgress(40, 'Exécution des scripts d\'installation…', $steps);
        }

        if ($installDoctor && $installCrashAnalyzer) {
            $command = sprintf(
                'curl -fsSL %s/install/doctor-suite.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
                $panelUrl,
                $panelUrl,
                $server->id,
                $server->agent_token,
            );
            $result = $this->runRemote($connection, $command);
            $steps[] = [
                'component' => 'doctor_suite',
                'success' => $result['success'],
                'output' => $result['output'],
            ];
            if ($onProgress !== null) {
                $onProgress(85, 'Finalisation Doctor & Crash Analyzer…', $steps);
            }
        } else {
            if ($installDoctor) {
                $command = $this->doctor->remoteCommand($server);
                $result = $this->runRemote($connection, $command);
                $steps[] = ['component' => 'doctor', 'success' => $result['success'], 'output' => $result['output']];
                if ($onProgress !== null) {
                    $onProgress(65, 'Installation ObiOra Doctor…', $steps);
                }
            }
            if ($installCrashAnalyzer) {
                $command = sprintf(
                    'curl -fsSL %s/install/crash-analyzer.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
                    $panelUrl,
                    $panelUrl,
                    $server->id,
                    $server->agent_token,
                );
                $result = $this->runRemote($connection, $command);
                $steps[] = ['component' => 'crash_analyzer', 'success' => $result['success'], 'output' => $result['output']];
                if ($onProgress !== null) {
                    $onProgress(85, 'Installation Crash Analyzer…', $steps);
                }
            }
        }

        $success = $steps !== [] && collect($steps)->every(fn (array $s) => $s['success']);

        if ($success) {
            $server->forceFill([
                'metadata' => array_merge($server->metadata ?? [], [
                    'doctor_deploy' => [
                        'deployed_at' => now()->toIso8601String(),
                        'method' => 'ssh_key',
                        'components' => array_column($steps, 'component'),
                    ],
                ]),
            ])->save();
            if ($onProgress !== null) {
                $onProgress(95, 'Enregistrement du déploiement…', $steps);
            }
        }

        return ['success' => $success, 'steps' => $steps];
    }

    /**
     * @return array{success: bool, output: string, message: string}
     */
    public function testConnection(SshConnection $connection): array
    {
        $result = $this->runRemote($connection, 'echo OBIORA_SSH_OK && hostname -f 2>/dev/null || hostname');

        $ok = $result['success'] && str_contains($result['output'], 'OBIORA_SSH_OK');

        return [
            'success' => $ok,
            'output' => $result['output'],
            'message' => $ok
                ? 'Connexion SSH réussie — '.trim(str_replace('OBIORA_SSH_OK', '', $result['output']))
                : ($result['output'] ?: 'Connexion SSH refusée ou timeout.'),
        ];
    }

    /**
     * @return array{success: bool, output: string, exit_code: int}
     */
    public function runRemote(SshConnection $connection, string $remoteCommand): array
    {
        return $this->ssh->run($connection, $remoteCommand);
    }
}
