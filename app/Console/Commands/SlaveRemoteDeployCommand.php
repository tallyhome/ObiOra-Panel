<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Servers\SlaveDeployRunner;
use Illuminate\Console\Command;

final class SlaveRemoteDeployCommand extends Command
{
    protected $signature = 'obiora:slave-deploy
                            {serverId : ID du serveur panel}
                            {host : Hôte SSH du VPS}
                            {port : Port SSH}
                            {user : Utilisateur SSH}';

    protected $description = 'Installe l\'agent seedbox slave sur un VPS distant (processus arrière-plan)';

    public function handle(SlaveDeployRunner $runner): int
    {
        set_time_limit(0);

        $runner->run(
            serverId: (int) $this->argument('serverId'),
            sshHost: (string) $this->argument('host'),
            sshPort: (int) $this->argument('port'),
            sshUser: (string) $this->argument('user'),
        );

        return self::SUCCESS;
    }
}
