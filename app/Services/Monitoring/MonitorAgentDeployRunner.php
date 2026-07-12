<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Services\Diagnostics\DoctorRemoteDeployService;
use App\Services\Diagnostics\RemoteOsDetector;
use App\Services\Diagnostics\ServerSshKeyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class MonitorAgentDeployRunner
{
    public function __construct(
        private readonly MonitorAgentDeployProgressService $progress,
        private readonly MonitorAgentRemoteDeployService $deploy,
        private readonly ServerSshKeyService $sshKeys,
        private readonly DoctorRemoteDeployService $doctorDeploy,
        private readonly RemoteOsDetector $osDetector,
    ) {}

    public function run(int $serverId, string $sshHost, int $sshPort, string $sshUser): void
    {
        $server = Server::query()->find($serverId);

        if ($server === null) {
            $this->progress->finish($serverId, false, 'Serveur introuvable.');

            return;
        }

        try {
            $this->progress->appendLog($serverId, "Cible : {$sshUser}@{$sshHost}:{$sshPort}");

            $bootstrapPassword = $this->pullBootstrapPassword($serverId);

            $server = $this->ensureSshKeyReady(
                $server,
                $sshHost,
                $sshPort,
                $sshUser,
                $bootstrapPassword,
            );

            $ssh = $this->sshKeys->connection($server, $sshHost, $sshPort, $sshUser);

            if ($ssh === null) {
                throw new \RuntimeException('Clé SSH indisponible après préparation.');
            }

            $this->progress->update($serverId, 15, 'Test de connexion SSH…');
            $test = $this->deploy->testConnection($ssh);

            if (! $test['success']) {
                throw new \RuntimeException($test['message'] ?: 'Connexion SSH échouée.');
            }

            $this->progress->appendLog($serverId, 'Connexion SSH OK.');

            $os = $this->osDetector->detect($ssh);
            if ($os !== null) {
                $server->update([
                    'os_name' => $os['name'],
                    'os_version' => $os['version'],
                    'ip_address' => $server->ip_address === '127.0.0.1' || $server->ip_address === ''
                        ? $sshHost
                        : $server->ip_address,
                ]);
                $this->progress->appendLog($serverId, 'OS détecté : '.$os['name'].($os['version'] ? ' '.$os['version'] : ''));
            }

            $result = $this->deploy->deployMonitorAgent(
                $server,
                $ssh,
                function (int $pct, string $msg) use ($serverId): void {
                    $this->progress->update($serverId, $pct, $msg);
                    $this->progress->appendLog($serverId, $msg);
                },
            );

            if ($result['output'] !== '') {
                foreach (explode("\n", $result['output']) as $line) {
                    if (trim($line) !== '') {
                        $this->progress->appendLog($serverId, $line);
                    }
                }
            }

            if (! $result['success']) {
                throw new \RuntimeException('Installation agent monitor échouée — voir la console.');
            }

            $this->progress->update($serverId, 95, 'En attente des premières métriques…');

            $server->refresh();
            $server->forceFill([
                'ip_address' => $sshHost,
                'hostname' => $server->hostname ?: $sshHost,
                'metadata' => array_merge($server->metadata ?? [], [
                    'monitor_agent' => [
                        'deployed_at' => now()->toIso8601String(),
                        'method' => 'ssh',
                        'remote_host' => $sshHost,
                        'version' => '1.0.0',
                    ],
                ]),
            ])->save();

            $this->progress->finish(
                $serverId,
                true,
                'Agent métriques installé — données attendues sous 1–2 minutes.',
                $result['output'],
            );
        } catch (\Throwable $e) {
            $this->progress->appendLog($serverId, 'ERREUR : '.$e->getMessage());
            $this->progress->finish($serverId, false, $e->getMessage(), $e->getMessage());
        } finally {
            Cache::forget($this->bootstrapCacheKey($serverId));
        }
    }

    public function storeBootstrapPassword(int $serverId, string $password): void
    {
        if ($password === '') {
            return;
        }

        Cache::put(
            $this->bootstrapCacheKey($serverId),
            Crypt::encryptString($password),
            now()->addMinutes(15),
        );
    }

    private function pullBootstrapPassword(int $serverId): ?string
    {
        $encrypted = Cache::pull($this->bootstrapCacheKey($serverId));

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function bootstrapCacheKey(int $serverId): string
    {
        return 'monitor_agent_deploy_bootstrap:'.$serverId;
    }

    private function ensureSshKeyReady(
        Server $server,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        ?string $bootstrapPassword,
    ): Server {
        if (! $this->sshKeys->hasKey($server)) {
            $this->progress->appendLog($server->id, 'Génération de la clé SSH dédiée…');
            $this->sshKeys->generate($server);
            $server = $server->fresh() ?? $server;
        }

        if ($this->sshKeys->isInstalledOnRemote($server)) {
            return $server;
        }

        if ($bootstrapPassword === null || $bootstrapPassword === '') {
            throw new \RuntimeException('Mot de passe SSH requis pour la première installation sur ce serveur.');
        }

        $connection = new SshConnection(
            host: $sshHost,
            port: $sshPort,
            username: $sshUser,
            password: $bootstrapPassword,
        );

        $result = $this->sshKeys->installPublicKeyOnRemote($server, $connection, $this->doctorDeploy);

        if (! $result['success']) {
            throw new \RuntimeException($result['output'] ?: 'Impossible d\'installer la clé SSH sur le serveur distant.');
        }

        $this->progress->appendLog($server->id, 'Clé SSH installée sur le serveur distant.');

        return $server->fresh() ?? $server;
    }
}
