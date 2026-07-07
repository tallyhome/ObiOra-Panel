<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use App\Models\UpdateHistory;
use Illuminate\Support\Facades\File;

final class PanelUpdater
{
    public function __construct(
        private readonly SystemExecutorInterface $executor,
        private readonly UpdateManager $updateManager,
    ) {}

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function apply(): array
    {
        if (! $this->canUpdate()) {
            return [
                'success' => false,
                'message' => 'Les mises à jour panel ne sont disponibles que sur une installation Linux en /opt/obiora-panel.',
                'output' => '',
            ];
        }

        $check = $this->updateManager->checkForUpdates();

        if (! ($check['available'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Aucune mise à jour disponible.',
                'output' => '',
            ];
        }

        $fromVersion = (string) config('obiora.version');
        $toVersion = (string) ($check['latest'] ?? $fromVersion);
        $panelRoot = base_path();

        $history = UpdateHistory::query()->create([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'status' => 'running',
            'changelog_url' => $check['changelog_url'] ?? null,
        ]);

        $commands = [
            "cd {$panelRoot} && git fetch origin main",
            "cd {$panelRoot} && git checkout main && git pull --ff-only origin main",
            "cd {$panelRoot} && sudo -u obiora env PATH=/usr/local/bin:/usr/bin:/bin composer install --no-dev --optimize-autoloader --no-interaction",
            "cd {$panelRoot} && sudo -u obiora php artisan migrate --force",
            "cd {$panelRoot} && sudo -u obiora php artisan optimize",
        ];

        $output = '';

        foreach ($commands as $command) {
            $result = $this->executor->run($command, ['timeout' => 600]);
            $output .= $result->output()."\n".$result->errorOutput()."\n";

            if (! $result->successful()) {
                $history->update([
                    'status' => 'failed',
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Échec de la mise à jour. Consultez l\'historique.',
                    'output' => $output,
                ];
            }
        }

        $history->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Mise à jour vers v{$toVersion} terminée. Rechargez la page.",
            'output' => $output,
        ];
    }

    public function canUpdate(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $panelRoot = base_path();

        return File::isDirectory($panelRoot.'/.git')
            && is_writable($panelRoot)
            && str_starts_with($panelRoot, '/opt/');
    }
}
