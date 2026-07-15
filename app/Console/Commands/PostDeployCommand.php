<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class PostDeployCommand extends Command
{
    protected $signature = 'obiora:post-deploy
                            {--skip-migrate : Ne pas exécuter les migrations}';

    protected $description = 'Tâches post-déploiement : migrations, RBAC, politiques d\'alerte, caches, scripts agent (exécuté automatiquement à l\'install et lors des MAJ)';

    public function handle(): int
    {
        if (! $this->option('skip-migrate')) {
            $this->components->task('Migrations', fn () => $this->callSilent('migrate', ['--force' => true]) === self::SUCCESS);
        }

        $this->components->task(
            'Sync rôles et permissions',
            fn () => $this->callSilent('db:seed', [
                '--class' => 'Database\\Seeders\\RolePermissionSeeder',
                '--force' => true,
            ]) === self::SUCCESS
        );

        $this->components->task(
            'Politiques d\'alerte par défaut',
            fn () => $this->callSilent('db:seed', [
                '--class' => 'Database\\Seeders\\AlertPolicySeeder',
                '--force' => true,
            ]) === self::SUCCESS
        );

        $this->components->task(
            'Serveur maître et réglages installation',
            function (): bool {
                app(\App\Services\Core\MasterServerSync::class)->ensure();

                return $this->callSilent('db:seed', [
                    '--class' => 'Database\\Seeders\\SettingsSeeder',
                    '--force' => true,
                ]) === self::SUCCESS;
            }
        );

        $this->components->task(
            'Cache permissions',
            fn () => $this->callSilent('permission:cache-reset') === self::SUCCESS
        );

        $this->components->task(
            'Purge caches Laravel',
            fn () => $this->callSilent('optimize:clear') === self::SUCCESS
        );

        $this->components->task(
            'Scripts agent exécutables',
            function (): bool {
                \App\Support\AgentScripts::ensureExecutable();

                return true;
            }
        );

        $this->newLine();
        $this->info('Post-déploiement terminé.');

        return self::SUCCESS;
    }
}
