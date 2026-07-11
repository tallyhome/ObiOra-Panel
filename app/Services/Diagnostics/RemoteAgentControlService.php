<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Support\PanelLocalTarget;

/**
 * Liste, arrête et désinstalle les agents diagnostics via SSH.
 */
final class RemoteAgentControlService
{
    /** @var list<string> */
    private const SERVICE_PATTERNS = [
        'crashhunter.service',
        'obiora-crash-analyzer.service',
        'obiora-doctor-agent.service',
        'obiora-doctor-agent.timer',
        'obiora-agent.service',
    ];

    public function __construct(
        private readonly SshRemoteExecutor $ssh,
        private readonly ServerSshKeyService $sshKeys,
        private readonly LocalDoctorDeployService $localDeploy,
    ) {}

    /**
     * @return array{success: bool, services: list<array<string, mixed>>, message: string}
     */
    public function listAgents(
        Server $server,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        ?SshConnection $connection = null,
    ): array {
        $command = 'systemctl list-units crashhunter.service obiora-crash-analyzer.service obiora-doctor-agent.service obiora-doctor-agent.timer obiora-agent.service --all --no-pager --plain 2>/dev/null || systemctl list-units \'obiora-*\' \'crashhunter*\' --all --no-pager --plain 2>/dev/null';

        $result = $this->runOnServer($server, $sshHost, $sshPort, $sshUser, $command, $connection);

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
    public function stopAllDiagnostics(
        Server $server,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        ?SshConnection $connection = null,
    ): array {
        $services = implode(' ', array_map(
            static fn (string $s) => escapeshellarg(str_replace('.service', '', str_replace('.timer', '', $s))),
            self::SERVICE_PATTERNS,
        ));

        $command = sprintf(
            'for u in %s; do systemctl stop "$u" 2>/dev/null || true; systemctl disable "$u" 2>/dev/null || true; done; echo OBIORA_AGENTS_STOPPED',
            $services,
        );

        $result = $this->runOnServer($server, $sshHost, $sshPort, $sshUser, $command, $connection, 120);

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
     * @return array{success: bool, message: string, output: string}
     */
    public function purgeAllDiagnostics(
        Server $server,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        ?SshConnection $connection = null,
        bool $purgeSlave = true,
    ): array {
        if (PanelLocalTarget::isPanelServer($server, $sshHost)) {
            $script = base_path('agent/scripts/uninstall-doctor-suite.sh');
            if (! is_readable($script)) {
                return ['success' => false, 'message' => 'Script de désinstallation introuvable.', 'output' => ''];
            }

            $result = $this->localDeploy->runCommand('bash '.escapeshellarg($script), 300);
            $ok = $result['success'] && str_contains($result['output'], 'OBIORA_SUITE_PURGED');

            if ($ok) {
                $this->clearDiagnosticsMetadata($server);
            }

            return [
                'success' => $ok,
                'message' => $ok
                    ? 'Agents supprimés et fichiers diagnostics nettoyés sur le serveur local.'
                    : ($result['output'] ?: 'Échec du nettoyage.'),
                'output' => $result['output'],
            ];
        }

        $panelUrl = rtrim((string) config('app.url'), '/');
        $command = sprintf(
            'curl -fsSL %s/install/uninstall-doctor-suite.sh | sudo OBIORA_PURGE_SLAVE=%s bash',
            $panelUrl,
            $purgeSlave ? 'yes' : 'no',
        );

        $result = $this->runOnServer($server, $sshHost, $sshPort, $sshUser, $command, $connection, 300);
        $ok = $result['success'] && str_contains($result['output'], 'OBIORA_SUITE_PURGED');

        if ($ok) {
            $this->clearDiagnosticsMetadata($server);
        }

        return [
            'success' => $ok,
            'message' => $ok
                ? 'Agents supprimés — services, logs, snapshots et répertoires diagnostics effacés.'
                : ($result['output'] ?: 'Échec du nettoyage complet.'),
            'output' => $result['output'],
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function runOnServer(
        Server $server,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        string $command,
        ?SshConnection $connection = null,
        int $timeout = 300,
    ): array {
        if (PanelLocalTarget::isPanelServer($server, $sshHost)) {
            $local = $this->localDeploy->runCommand($command, $timeout);

            return [
                'success' => ($local['exit_code'] ?? 1) === 0,
                'output' => (string) ($local['output'] ?? ''),
            ];
        }

        $connection ??= $this->resolveConnection($server, $sshHost, $sshPort, $sshUser);

        if ($connection === null) {
            return ['success' => false, 'output' => 'Connexion SSH indisponible — retestez avec le mot de passe root.'];
        }

        $result = $this->ssh->run($connection, $command, $timeout);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
        ];
    }

    private function resolveConnection(Server $server, string $sshHost, int $sshPort, string $sshUser): ?SshConnection
    {
        if ($this->sshKeys->keyAppliesToHost($server, $sshHost)) {
            return $this->sshKeys->connection($server, $sshHost, $sshPort, $sshUser);
        }

        return null;
    }

    private function clearDiagnosticsMetadata(Server $server): void
    {
        $meta = $server->metadata ?? [];
        unset(
            $meta['doctor_deploy'],
            $meta['crash_hunter'],
            $meta['diagnostics_agents_stopped_at'],
            $meta['agent_installed'],
            $meta['slave_deploy'],
        );

        if (isset($meta['ssh_deploy']) && is_array($meta['ssh_deploy'])) {
            unset($meta['ssh_deploy']['installed_on_remote_at']);
        }

        $server->forceFill([
            'metadata' => $meta,
            'status' => 'offline',
        ])->save();
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
