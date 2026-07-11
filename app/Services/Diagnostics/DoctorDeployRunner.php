<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;
use App\Models\Server;
use App\Support\PanelLocalTarget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Exécute le déploiement Doctor & Crash Analyzer sur un serveur distant (dédié ou VPS).
 */
final class DoctorDeployRunner
{
    public function __construct(
        private readonly DoctorDeployProgressService $progress,
        private readonly DoctorRemoteDeployService $deploy,
        private readonly LocalDoctorDeployService $localDeploy,
        private readonly ServerSshKeyService $sshKeys,
    ) {}

    public function run(
        int $serverId,
        string $sshHost,
        int $sshPort,
        string $sshUser,
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        bool $installCrashHunter = true,
    ): void {
        $server = Server::query()->find($serverId);

        if ($server === null) {
            $this->progress->finish($serverId, false, 'Serveur introuvable.', [], 'Serveur introuvable.');

            return;
        }

        try {
            $this->progress->update($serverId, 8, 'Worker de déploiement démarré…');
            $this->progress->appendLog($serverId, "Cible : {$sshUser}@{$sshHost}:{$sshPort}");

            if (PanelLocalTarget::isPanelServer($server, $sshHost)) {
                $this->runLocalDeploy($server, $installDoctor, $installCrashAnalyzer, $installCrashHunter);

                return;
            }

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
            $this->progress->appendLog($serverId, 'Test connexion SSH…');

            $test = $this->deploy->testConnection($ssh);
            if (! $test['success']) {
                throw new \RuntimeException($test['message'] ?: $test['output'] ?: 'Connexion SSH échouée.');
            }

            $this->progress->appendLog($serverId, 'Connexion OK — '.$test['message']);

            $this->progress->update($serverId, 25, 'Téléchargement des scripts depuis le panel…');
            $this->progress->appendLog($serverId, 'Envoi des scripts d\'installation (Doctor + Crash Analyzer)…');

            $result = $this->deploy->deploySuite(
                $server,
                $ssh,
                $installDoctor,
                $installCrashAnalyzer,
                $installCrashHunter,
                function (int $pct, string $msg, array $steps) use ($serverId): void {
                    $this->progress->update($serverId, $pct, $msg, $steps);
                    $this->progress->appendLog($serverId, $msg);

                    foreach ($steps as $step) {
                        $status = ($step['success'] ?? false) ? 'OK' : 'ÉCHEC';
                        $this->progress->appendLog(
                            $serverId,
                            "[{$step['component']}] {$status}",
                        );
                    }
                },
            );

            $log = collect($result['steps'])->pluck('output')->filter()->implode("\n\n");

            foreach ($result['steps'] as $step) {
                if (($step['output'] ?? '') !== '') {
                    $this->progress->appendLog($serverId, "--- {$step['component']} ---");
                    foreach (explode("\n", (string) $step['output']) as $line) {
                        if (trim($line) !== '') {
                            $this->progress->appendLog($serverId, $line);
                        }
                    }
                }
            }

            if ($result['success']) {
                $this->progress->appendLog($serverId, 'Installation terminée avec succès.');
                $this->progress->finish(
                    $serverId,
                    true,
                    'Installation terminée — les agents envoient les données au panel.',
                    $result['steps'],
                    $log,
                );
            } else {
                $this->progress->appendLog($serverId, 'Une ou plusieurs étapes ont échoué.');
                $this->progress->finish(
                    $serverId,
                    false,
                    'Échec du déploiement — consultez la console ci-dessous.',
                    $result['steps'],
                    $log,
                );
            }
        } catch (\Throwable $e) {
            $this->progress->appendLog($serverId, 'ERREUR : '.$e->getMessage());
            $this->progress->finish(
                $serverId,
                false,
                $e->getMessage(),
                [],
                $e->getMessage(),
            );
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
        return 'doctor_deploy_bootstrap:'.$serverId;
    }

    private function runLocalDeploy(Server $server, bool $installDoctor, bool $installCrashAnalyzer, bool $installCrashHunter = true): void
    {
        $serverId = $server->id;

        $this->progress->appendLog($serverId, 'Serveur local du panel — installation directe (sans SSH).');
        $this->progress->update($serverId, 15, 'Vérification environnement local…');

        $test = $this->localDeploy->testLocal();
        if (! $test['success']) {
            throw new \RuntimeException($test['message'] ?: 'Environnement local indisponible.');
        }

        $this->progress->appendLog($serverId, $test['message']);

        $result = $this->localDeploy->deploySuite(
            $server,
            $installDoctor,
            $installCrashAnalyzer,
            $installCrashHunter,
            function (int $pct, string $msg, array $steps) use ($serverId): void {
                $this->progress->update($serverId, $pct, $msg, $steps);
                $this->progress->appendLog($serverId, $msg);

                foreach ($steps as $step) {
                    $status = ($step['success'] ?? false) ? 'OK' : 'ÉCHEC';
                    $this->progress->appendLog($serverId, "[{$step['component']}] {$status}");
                }
            },
        );

        $log = collect($result['steps'])->pluck('output')->filter()->implode("\n\n");

        foreach ($result['steps'] as $step) {
            if (($step['output'] ?? '') !== '') {
                $this->progress->appendLog($serverId, "--- {$step['component']} ---");
                foreach (explode("\n", (string) $step['output']) as $line) {
                    if (trim($line) !== '') {
                        $this->progress->appendLog($serverId, $line);
                    }
                }
            }
        }

        if ($result['success']) {
            $this->progress->appendLog($serverId, 'Installation locale terminée avec succès.');
            $this->progress->finish(
                $serverId,
                true,
                'Installation terminée — les agents envoient les données au panel.',
                $result['steps'],
                $log,
            );
        } else {
            $this->progress->appendLog($serverId, 'Une ou plusieurs étapes ont échoué.');
            $this->progress->finish(
                $serverId,
                false,
                'Échec du déploiement local — consultez la console ci-dessous.',
                $result['steps'],
                $log,
            );
        }
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
            $this->progress->appendLog($server->id, 'Clé SSH créée.');
        }

        if ($this->sshKeys->isInstalledOnRemote($server)) {
            $this->progress->appendLog($server->id, 'Clé SSH déjà installée sur le serveur distant.');

            return $server;
        }

        if (PanelLocalTarget::isPanelServer($server, $sshHost)) {
            $this->progress->appendLog($server->id, 'Installation de la clé SSH sur le serveur local…');
            $result = $this->sshKeys->installPublicKeyLocally($server);

            if (! $result['success']) {
                throw new \RuntimeException($result['output'] ?: 'Impossible d\'installer la clé SSH localement.');
            }

            $this->progress->appendLog($server->id, 'Clé SSH installée localement.');

            return $server->fresh() ?? $server;
        }

        if ($bootstrapPassword === null || $bootstrapPassword === '') {
            throw new \RuntimeException('Mot de passe SSH requis pour la première installation sur ce serveur distant.');
        }

        $this->progress->appendLog($server->id, 'Installation de la clé SSH sur le serveur distant (mot de passe)…');

        $connection = new SshConnection(
            host: $sshHost,
            port: $sshPort,
            username: $sshUser,
            password: $bootstrapPassword,
        );

        $result = $this->sshKeys->installPublicKeyOnRemote($server, $connection, $this->deploy);

        if (! $result['success']) {
            throw new \RuntimeException($result['output'] ?: 'Impossible d\'installer la clé SSH sur le serveur distant.');
        }

        $this->progress->appendLog($server->id, 'Clé SSH installée sur le serveur distant.');

        return $server->fresh() ?? $server;
    }
}
