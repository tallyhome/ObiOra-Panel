<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Contracts\SystemExecutorInterface;
use App\Models\Server;
use App\Support\DoctorInstallHelper;

/**
 * Installe Doctor / Crash Analyzer sur le serveur local du panel (sans SSH).
 */
final class LocalDoctorDeployService
{
    public function __construct(
        private readonly SystemExecutorInterface $executor,
        private readonly DoctorInstallHelper $doctor,
    ) {}

    /**
     * @return array{success: bool, output: string, message: string}
     */
    public function testLocal(): array
    {
        $hostname = gethostname() ?: 'localhost';

        return [
            'success' => true,
            'output' => "OBIORA_SSH_OK\n{$hostname}",
            'message' => 'Serveur local (panel) — prêt pour installation directe.',
        ];
    }

    /**
     * @param  callable(int, string, list<array{component: string, success: bool, output: string}>): void|null  $onProgress
     * @return array{success: bool, steps: list<array{component: string, success: bool, output: string}>}
     */
    public function deploySuite(
        Server $server,
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        ?callable $onProgress = null,
    ): array {
        $steps = [];
        $panelUrl = rtrim((string) config('app.url'), '/');

        if ($onProgress !== null) {
            $onProgress(30, 'Installation locale (sans SSH)…', $steps);
        }

        if ($installDoctor && $installCrashAnalyzer) {
            $command = sprintf(
                'curl -fsSL %s/install/doctor-suite.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
                escapeshellarg($panelUrl),
                escapeshellarg($panelUrl),
                $server->id,
                escapeshellarg($server->agent_token),
            );
            $result = $this->runShell($command, 900);
            $steps[] = [
                'component' => 'doctor_suite',
                'success' => $result['success'],
                'output' => $result['output'],
            ];
        } else {
            if ($installDoctor) {
                $command = $this->doctor->remoteCommand($server);
                $result = $this->runShell($command, 600);
                $steps[] = ['component' => 'doctor', 'success' => $result['success'], 'output' => $result['output']];
                if ($onProgress !== null) {
                    $onProgress(55, 'Installation ObiOra Doctor…', $steps);
                }
            }
            if ($installCrashAnalyzer) {
                $command = sprintf(
                    'curl -fsSL %s/install/crash-analyzer.sh | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
                    escapeshellarg($panelUrl),
                    escapeshellarg($panelUrl),
                    $server->id,
                    escapeshellarg($server->agent_token),
                );
                $result = $this->runShell($command, 600);
                $steps[] = ['component' => 'crash_analyzer', 'success' => $result['success'], 'output' => $result['output']];
                if ($onProgress !== null) {
                    $onProgress(75, 'Installation Crash Analyzer…', $steps);
                }
            }
        }

        $success = $steps !== [] && collect($steps)->every(fn (array $s) => $s['success']);

        if ($success) {
            $server->forceFill([
                'metadata' => array_merge($server->metadata ?? [], [
                    'doctor_deploy' => [
                        'deployed_at' => now()->toIso8601String(),
                        'method' => 'local',
                        'remote_host' => $server->ip_address,
                        'components' => array_column($steps, 'component'),
                    ],
                ]),
            ])->save();

            if ($onProgress !== null) {
                $onProgress(95, 'Enregistrement du déploiement local…', $steps);
            }
        }

        return ['success' => $success, 'steps' => $steps];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function runShell(string $command, int $timeout): array
    {
        $result = $this->executor->run($command, ['timeout' => $timeout]);
        $output = trim($result->output.$result->errorOutput);

        return [
            'success' => $result->successful,
            'output' => $output !== '' ? $output : ($result->successful ? 'OK' : 'Échec commande locale'),
        ];
    }
}
