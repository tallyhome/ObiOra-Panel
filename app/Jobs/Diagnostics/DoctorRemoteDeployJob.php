<?php

declare(strict_types=1);

namespace App\Jobs\Diagnostics;

use App\Models\Server;
use App\Services\Diagnostics\DoctorDeployProgressService;
use App\Services\Diagnostics\DoctorRemoteDeployService;
use App\Services\Diagnostics\ServerSshKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DoctorRemoteDeployJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly int $serverId,
        public readonly string $sshHost,
        public readonly int $sshPort,
        public readonly string $sshUser,
        public readonly bool $installDoctor = true,
        public readonly bool $installCrashAnalyzer = true,
    ) {}

    public function handle(
        DoctorRemoteDeployService $deploy,
        ServerSshKeyService $sshKeys,
        DoctorDeployProgressService $progress,
    ): void {
        $server = Server::query()->find($this->serverId);

        if ($server === null) {
            $progress->finish($this->serverId, false, 'Serveur introuvable.', [], 'Serveur introuvable.');

            return;
        }

        $ssh = $sshKeys->connection($server, $this->sshHost, $this->sshPort, $this->sshUser);

        if ($ssh === null) {
            $progress->finish(
                $this->serverId,
                false,
                'Clé SSH dédiée manquante. Générez et installez la clé avant le déploiement.',
                [],
            );

            return;
        }

        $progress->update($this->serverId, 15, 'Connexion SSH au serveur distant…');

        $test = $deploy->testConnection($ssh);
        if (! $test['success']) {
            $progress->finish($this->serverId, false, $test['message'], [], $test['output']);

            return;
        }

        $progress->update($this->serverId, 30, 'Téléchargement et exécution des scripts…');

        $result = $deploy->deploySuite(
            $server,
            $ssh,
            $this->installDoctor,
            $this->installCrashAnalyzer,
            function (int $pct, string $msg, array $steps) use ($progress): void {
                $progress->update($this->serverId, $pct, $msg, $steps);
            },
        );

        if ($result['success']) {
            $progress->finish(
                $this->serverId,
                true,
                'Installation terminée avec succès.',
                $result['steps'],
                collect($result['steps'])->pluck('output')->implode("\n"),
            );
        } else {
            $progress->finish(
                $this->serverId,
                false,
                'Une ou plusieurs étapes ont échoué.',
                $result['steps'],
                collect($result['steps'])->pluck('output')->implode("\n"),
            );
        }
    }
}
