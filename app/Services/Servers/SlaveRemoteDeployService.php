<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Services\Diagnostics\SshRemoteExecutor;
use App\Support\SlaveInstallHelper;

final class SlaveRemoteDeployService
{
    public function __construct(
        private readonly SlaveInstallHelper $slave,
        private readonly SshRemoteExecutor $ssh,
    ) {}

    /**
     * @return array{success: bool, output: string, message: string}
     */
    public function testConnection(SshConnection $connection): array
    {
        $result = $this->ssh->run($connection, 'echo OBIORA_SSH_OK && hostname -f 2>/dev/null || hostname');

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
     * @param  callable(int, string): void|null  $onProgress
     * @return array{success: bool, output: string}
     */
    public function deploySlave(Server $server, SshConnection $connection, ?callable $onProgress = null): array
    {
        if ($onProgress !== null) {
            $onProgress(40, 'Installation de l\'agent seedbox slave…');
        }

        $command = $this->slave->remoteCommand($server);
        $result = $this->ssh->run($connection, $command, 900);

        if ($onProgress !== null) {
            $onProgress(90, $result['success'] ? 'Agent installé — vérification…' : 'Échec installation agent');
        }

        return [
            'success' => $result['success'],
            'output' => $result['output'],
        ];
    }
}
