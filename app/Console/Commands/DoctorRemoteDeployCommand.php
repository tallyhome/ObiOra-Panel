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
                            {slave : Installer agent seedbox (yes/no)}
                            {upgrade=no : Mise à jour agents uniquement (yes/no)}
                            {components=none : Composants à mettre à jour (csv, ou none)}';

    protected $description = 'Déploie ou met à jour Doctor, Crash Analyzer, CrashHunter';

    public function handle(DoctorDeployRunner $runner): int
    {
        set_time_limit(0);

        $componentsArg = (string) ($this->argument('components') ?? 'none');
        $components = ($componentsArg !== '' && $componentsArg !== 'none')
            ? array_values(array_filter(array_map('trim', explode(',', $componentsArg))))
            : [];

        $runner->run(
            serverId: (int) $this->argument('serverId'),
            sshHost: (string) $this->argument('host'),
            sshPort: (int) $this->argument('port'),
            sshUser: (string) $this->argument('user'),
            installDoctor: $this->argument('doctor') === 'yes',
            installCrashAnalyzer: $this->argument('crash') === 'yes',
            installCrashHunter: $this->argument('crashhunter') === 'yes',
            installSlave: $this->argument('slave') === 'yes',
            upgradeOnly: ($this->argument('upgrade') ?? 'no') === 'yes',
            upgradeComponents: $components,
        );

        return self::SUCCESS;
    }
}
