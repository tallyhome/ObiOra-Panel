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
        bool $installCrashHunter = true,
        ?callable $onProgress = null,
    ): array {
        $steps = [];
        $panelUrl = rtrim((string) config('app.url'), '/');

        if ($onProgress !== null) {
            $onProgress(40, 'Exécution des scripts d\'installation…', $steps);
        }

        if ($installDoctor || $installCrashAnalyzer || $installCrashHunter) {
            $command = sprintf(
                'curl -fsSL %s/install/doctor-suite.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s OBIORA_INSTALL_DOCTOR=%s OBIORA_INSTALL_CRASH_ANALYZER=%s OBIORA_INSTALL_CRASH_HUNTER=%s bash',
                $panelUrl,
                $panelUrl,
                $server->id,
                $server->agent_token,
                $installDoctor ? 'yes' : 'no',
                $installCrashAnalyzer ? 'yes' : 'no',
                $installCrashHunter ? 'yes' : 'no',
            );
            $result = $this->runRemote($connection, $command);
            $steps[] = [
                'component' => 'doctor_suite',
                'success' => $result['success'],
                'output' => $result['output'],
            ];
            if ($installCrashHunter && $result['success']) {
                $steps[] = [
                    'component' => 'crash_hunter',
                    'success' => true,
                    'output' => 'CrashHunter inclus dans doctor-suite',
                ];
            }
            if ($onProgress !== null) {
                $onProgress(85, 'Finalisation Doctor & Suite…', $steps);
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
            if ($installCrashHunter) {
                $command = sprintf(
                    'curl -fsSL %s/install/crash-hunter.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
                    $panelUrl,
                    $panelUrl,
                    $server->id,
                    $server->agent_token,
                );
                $result = $this->runRemote($connection, $command);
                $steps[] = ['component' => 'crash_hunter', 'success' => $result['success'], 'output' => $result['output']];
            }
        }

        $success = $steps !== [] && collect($steps)->every(fn (array $s) => $s['success']);

        if ($success) {
            $remoteHost = $connection->host;
            $server->forceFill([
                'hostname' => $server->hostname ?: $remoteHost,
                'metadata' => array_merge($server->metadata ?? [], [
                    'doctor_deploy' => [
                        'deployed_at' => now()->toIso8601String(),
                        'method' => 'ssh_key',
                        'remote_host' => $remoteHost,
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
     * Met à jour les agents déjà installés (préserve config CrashHunter).
     *
     * @param  list<string>  $components  crash_hunter, crash_analyzer, doctor
     * @param  callable(int, string, list<array{component: string, success: bool, output: string}>): void|null  $onProgress
     * @return array{success: bool, steps: list<array{component: string, success: bool, output: string}>}
     */
    public function upgradeAgents(
        Server $server,
        SshConnection $connection,
        array $components,
        ?callable $onProgress = null,
    ): array {
        $steps = [];
        $panelUrl = rtrim((string) config('app.url'), '/');

        if ($components === []) {
            return ['success' => true, 'steps' => []];
        }

        if ($onProgress !== null) {
            $onProgress(35, 'Mise à jour des agents distants…', $steps);
        }

        $command = sprintf(
            'curl -fsSL %s/install/update-doctor-agents.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s OBIORA_UPDATE_CRASH_HUNTER=%s OBIORA_UPDATE_CRASH_ANALYZER=%s OBIORA_UPDATE_DOCTOR=%s bash',
            $panelUrl,
            $panelUrl,
            $server->id,
            $server->agent_token,
            in_array('crash_hunter', $components, true) ? 'yes' : 'no',
            in_array('crash_analyzer', $components, true) ? 'yes' : 'no',
            in_array('doctor', $components, true) ? 'yes' : 'no',
        );

        $result = $this->runRemote($connection, $command);
        $steps[] = [
            'component' => 'agent_upgrade',
            'success' => $result['success'],
            'output' => $result['output'],
        ];

        if ($result['success']) {
            $meta = $server->metadata ?? [];
            $meta['doctor_deploy'] = array_merge($meta['doctor_deploy'] ?? [], [
                'last_upgrade_at' => now()->toIso8601String(),
                'upgraded_components' => $components,
            ]);
            $server->forceFill(['metadata' => $meta])->save();
            if ($onProgress !== null) {
                $onProgress(95, 'Mise à jour enregistrée…', $steps);
            }
        }

        return [
            'success' => $result['success'],
            'steps' => $steps,
        ];
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
