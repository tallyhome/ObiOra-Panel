<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\SystemExecutorInterface;
use Illuminate\Console\Command;

final class RecoverPanelHttpCommand extends Command
{
    protected $signature = 'obiora:recover-panel-http
                            {--skip-systemd : Ne pas recharger php-fpm/nginx via systemd}';

    protected $description = 'Récupère le panel après une MAJ (maintenance, caches, 502 Bad Gateway)';

    public function handle(SystemExecutorInterface $executor): int
    {
        $this->components->task('Sortie maintenance', fn () => $this->callSilent('up') === self::SUCCESS);
        $this->components->task('Purge caches', fn () => $this->callSilent('optimize:clear') === self::SUCCESS);

        if (! $this->option('skip-systemd') && PHP_OS_FAMILY === 'Linux') {
            $script = base_path('install/lib/update-recover.sh');
            if (is_file($script)) {
                try {
                    if (posix_geteuid() === 0) {
                        $executor->run('/bin/bash '.escapeshellarg($script), ['timeout' => 120]);
                    } else {
                        $executor->run('sudo -n /bin/bash '.escapeshellarg($script), ['timeout' => 120]);
                    }
                } catch (\Throwable $e) {
                    $this->warn('Rechargement systemd ignoré : '.$e->getMessage());
                }
            }
        }

        $this->info('Récupération terminée.');

        return self::SUCCESS;
    }
}
