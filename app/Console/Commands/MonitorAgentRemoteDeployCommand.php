<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\MonitorAgentDeployRunner;
use Illuminate\Console\Command;

final class MonitorAgentRemoteDeployCommand extends Command
{
    protected $signature = 'obiora:monitor-agent-deploy
                            {serverId : ID du serveur panel}
                            {host : Hôte SSH}
                            {port : Port SSH}
                            {user : Utilisateur SSH}';

    protected $description = 'Installe l\'agent métriques ObiOra Monitor sur un serveur distant via SSH';

    public function handle(MonitorAgentDeployRunner $runner): int
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
