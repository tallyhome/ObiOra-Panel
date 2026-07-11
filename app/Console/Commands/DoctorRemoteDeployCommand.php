<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Diagnostics\DoctorDeployRunner;
use Illuminate\Console\Command;

final class DoctorRemoteDeployCommand extends Command
{
    protected $signature = 'obiora:doctor-deploy
                            {serverId : ID du serveur panel}
                            {host : Hôte SSH du serveur distant}
                            {port : Port SSH}
                            {user : Utilisateur SSH}
                            {doctor : Installer Doctor (yes/no)}
                            {crash : Installer Crash Analyzer (yes/no)}
                            {crashhunter : Installer CrashHunter (yes/no)}
                            {slave : Installer agent seedbox (yes/no)}';

    protected $description = 'Déploie Doctor, Crash Analyzer, CrashHunter et optionnellement l\'agent seedbox';

    public function handle(DoctorDeployRunner $runner): int
    {
        set_time_limit(0);

        $runner->run(
            serverId: (int) $this->argument('serverId'),
            sshHost: (string) $this->argument('host'),
            sshPort: (int) $this->argument('port'),
            sshUser: (string) $this->argument('user'),
            installDoctor: $this->argument('doctor') === 'yes',
            installCrashAnalyzer: $this->argument('crash') === 'yes',
            installCrashHunter: $this->argument('crashhunter') === 'yes',
            installSlave: $this->argument('slave') === 'yes',
        );

        return self::SUCCESS;
    }
}
