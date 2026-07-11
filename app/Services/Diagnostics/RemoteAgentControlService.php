<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Support\PanelLocalTarget;

/**
 * Liste et arrête les agents diagnostics (CrashHunter, Crash Analyzer, Doctor) via SSH.
 */
final class RemoteAgentControlService
{
    /** @var list<string> */
    private const SERVICE_PATTERNS = [
        'crashhunter.service',
        'obiora-crash-analyzer.service',
        'obiora-doctor-agent.service',
        'obiora-doctor-agent.timer',
    ];

    public function __construct(
        private readonly SshRemoteExecutor $ssh,
        private readonly ServerSshKeyService $sshKeys,
        private readonly LocalDoctorDeployService $localDeploy,
    ) {}

    /**
     * @return array{success: bool, services: list<array<string, mixed>>, message: string}
     */
    public function listAgents(Server $server, string $sshHost, int $sshPort, string $sshUser): array
    {
        $command = 'systemctl list-units crashhunter.service obiora-crash-analyzer.service obiora-doctor-agent.service obiora-doctor-agent.timer --all --no-pager --plain 2>/dev/null || systemctl list-units \'obiora-*\' \'crashhunter*\' --all --no-pager --plain 2>/dev/null';

        $result = $this->runOnServer($server, $sshHost, $sshPort, $sshUser, $command);

        if (! $result['success']) {
            return [
                'success' => false,
                'services' => [],
                'message' => $result['output'] ?: 'Impossible de lister les services.',
            ];
        }

        $services = $this->parseSystemctlList($result['output']);

        return [
            'success' => true,
            'services' => $services,
            'message' => count($services).' service(s) détecté(s).',
        ];
    }

    /**
     * @return array{success: bool, stopped: list<string>, message: string, output: string}
     */
    public function stopAllDiagnostics(Server $server, string $sshHost, int $sshPort, string $sshUser): array
    {
        $services = implode(' ', array_map(
            static fn (string $s) => escapeshellarg(str_replace('.service', '', str_replace('.timer', '', $s))),
            self::SERVICE_PATTERNS,
        ));

        $command = sprintf(
            'for u in %s; do systemctl stop "$u" 2>/dev/null || true; systemctl disable "$u" 2>/dev/null || true; done; echo OBIORA_AGENTS_STOPPED',
            $services,
        );

        $result = $this->runOnServer($server, $sshHost, $sshPort, $sshUser, $command);

        $stopped = [];
        if ($result['success'] && str_contains($result['output'], 'OBIORA_AGENTS_STOPPED')) {
            foreach (self::SERVICE_PATTERNS as $unit) {
                $stopped[] = $unit;
            }

            $meta = $server->metadata ?? [];
            $meta['diagnostics_agents_stopped_at'] = now()->toIso8601String();
            $server->forceFill(['metadata' => $meta])->save();
        }

        return [
            'success' => $result['success'] && str_contains($result['output'], 'OBIORA_AGENTS_STOPPED'),
            'stopped' => $stopped,
            'message' => $result['success']
                ? 'Agents diagnostics arrêtés et désactivés au boot.'
                : ($result['output'] ?: 'Échec arrêt agents.'),
            'output' => $result['output'],
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function runOnServer(Server $server, string $sshHost, int $sshPort, string $sshUser, string $command): array
    {
        if (PanelLocalTarget::isPanelServer($server, $sshHost)) {
            $local = $this->localDeploy->runCommand($command);

            return [
                'success' => ($local['exit_code'] ?? 1) === 0,
                'output' => (string) ($local['output'] ?? ''),
            ];
        }

        $connection = $this->sshKeys->connection($server, $sshHost, $sshPort, $sshUser);
        if ($connection === null) {
            return ['success' => false, 'output' => 'Clé SSH non configurée pour ce serveur.'];
        }

        $result = $this->ssh->run($connection, $command);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSystemctlList(string $output): array
    {
        $services = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'UNIT ')) {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 5);
            if ($parts === false || count($parts) < 4) {
                continue;
            }
            $unit = $parts[0];
            if (! str_contains($unit, 'crashhunter') && ! str_contains($unit, 'obiora-')) {
                continue;
            }
            $services[] = [
                'unit' => $unit,
                'load' => $parts[1] ?? '',
                'active' => $parts[2] ?? '',
                'sub' => $parts[3] ?? '',
                'description' => $parts[4] ?? '',
                'running' => ($parts[2] ?? '') === 'active',
            ];
        }

        return $services;
    }
}
