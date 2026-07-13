<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Contracts\SystemExecutorInterface;
use App\Models\Server;
use App\Services\Diagnostics\DiagnosticsAgentVersionService;
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
        bool $installCrashHunter = true,
        ?callable $onProgress = null,
    ): array {
        $steps = [];
        $panelUrl = rtrim((string) config('app.url'), '/');

        if ($onProgress !== null) {
            $onProgress(30, 'Installation locale (sans SSH)…', $steps);
        }

        $command = $this->doctor->suiteInstallShellCommand(
            $server,
            $installDoctor,
            $installCrashAnalyzer,
            $installCrashHunter,
        );
        $result = $this->runShell($command, 900);
        $steps[] = [
            'component' => 'doctor_suite',
            'success' => $result['success'],
            'output' => $result['output'],
        ];

        $success = $steps !== [] && collect($steps)->every(fn (array $s) => $s['success']);

        if ($success) {
            $installedAgents = $this->doctor->suiteComponentList(
                $installDoctor,
                $installCrashAnalyzer,
                $installCrashHunter,
            );
            app(DiagnosticsAgentVersionService::class)->stampDeployedVersions($server, $installedAgents);

            $server->forceFill([
                'metadata' => array_merge($server->metadata ?? [], [
                    'doctor_deploy' => [
                        'deployed_at' => now()->toIso8601String(),
                        'method' => 'local',
                        'remote_host' => $server->ip_address,
                        'components' => $installedAgents,
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
     * @return array{success: bool, output: string, exit_code: int}
     */
    public function runCommand(string $command, int $timeout = 120): array
    {
        $result = $this->runShell($command, $timeout);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'exit_code' => $result['success'] ? 0 : 1,
        ];
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
