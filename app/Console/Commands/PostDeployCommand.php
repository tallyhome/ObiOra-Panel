<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class PostDeployCommand extends Command
{
    protected $signature = 'obiora:post-deploy
                            {--skip-migrate : Ne pas exécuter les migrations}';

    protected $description = 'Tâches post-déploiement : migrations, sync RBAC, reset cache permissions';

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
            'Cache permissions',
            fn () => $this->callSilent('permission:cache-reset') === self::SUCCESS
        );

        $this->newLine();
        $this->info('Post-déploiement terminé.');

        return self::SUCCESS;
    }
}
