<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Support\DoctorInstallHelper;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Déploie Doctor et Crash Analyzer via SSH.
 * Après bootstrap, seule la clé SSH dédiée ou le jeton agent API est utilisé.
 */
final class DoctorRemoteDeployService
{
    public function __construct(
        private readonly DoctorInstallHelper $doctor,
    ) {}

    /**
     * @param  callable(int, string, list<array{component: string, success: bool, output: string}>): void|null  $onProgress
     * @return array{success: bool, steps: list<array{component: string, success: bool, output: string}>}
     */
    public function deploySuite(
        Server $server,
        SshConnection $ssh,
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
            $result = $this->runRemote($ssh, $command);
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
                $result = $this->runRemote($ssh, $command);
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
                $result = $this->runRemote($ssh, $command);
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
    public function testConnection(SshConnection $ssh): array
    {
        $result = $this->runRemote($ssh, 'echo OBIORA_SSH_OK && hostname -f 2>/dev/null || hostname');

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
    public function runRemote(SshConnection $ssh, string $remoteCommand): array
    {
        $keyFile = null;

        try {
            $sshArgs = [
                '-p', (string) $ssh->port,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'ConnectTimeout=30',
                '-o', 'LogLevel=ERROR',
            ];

            if ($ssh->privateKey !== null && $ssh->privateKey !== '') {
                $keyFile = tempnam(sys_get_temp_dir(), 'obiora_ssh_');
                if ($keyFile === false) {
                    return ['success' => false, 'output' => 'Impossible de créer un fichier clé temporaire.', 'exit_code' => 1];
                }
                file_put_contents($keyFile, $ssh->privateKey);
                chmod($keyFile, 0600);
                $sshArgs[] = '-i';
                $sshArgs[] = $keyFile;
                $sshArgs[] = '-o';
                $sshArgs[] = 'BatchMode=yes';
            }

            $sshArgs[] = $ssh->username.'@'.$ssh->host;
            $sshArgs[] = $remoteCommand;

            if ($ssh->password !== null && $ssh->password !== '' && ($ssh->privateKey === null || $ssh->privateKey === '')) {
                if (! $this->hasSshpass()) {
                    return [
                        'success' => false,
                        'output' => 'Authentification par mot de passe : installez sshpass sur le serveur panel (apt install sshpass / dnf install sshpass).',
                        'exit_code' => 1,
                    ];
                }
                $process = new Process(
                    array_merge(['sshpass', '-e', 'ssh'], $sshArgs),
                    null,
                    ['SSHPASS' => $ssh->password],
                );
            } else {
                $process = new Process(array_merge(['ssh'], $sshArgs));
            }

            $process->setTimeout(300);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
                'exit_code' => $process->getExitCode() ?? 1,
            ];
        } catch (\Throwable $e) {
            Log::warning('Doctor SSH deploy failed', ['host' => $ssh->host, 'error' => $e->getMessage()]);

            return ['success' => false, 'output' => $e->getMessage(), 'exit_code' => 1];
        } finally {
            if ($keyFile !== null && is_file($keyFile)) {
                @unlink($keyFile);
            }
        }
    }

    private function hasSshpass(): bool
    {
        $process = new Process(['which', 'sshpass']);
        $process->run();

        return $process->isSuccessful();
    }
}
