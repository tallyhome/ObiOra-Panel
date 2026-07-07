<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use App\Models\UpdateHistory;
use App\Support\InstalledVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PanelUpdater
{
    public function __construct(
        private readonly SystemExecutorInterface $executor,
        private readonly UpdateManager $updateManager,
        private readonly InstalledVersion $installedVersion,
    ) {}

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function apply(): array
    {
        if (! $this->canUpdate()) {
            return [
                'success' => false,
                'message' => 'Mise à jour indisponible : dépôt git introuvable ou script update-panel.sh absent.',
                'output' => '',
            ];
        }

        $check = $this->updateManager->checkForUpdates(fresh: true);

        if (! ($check['available'] ?? false)) {
            return [
                'success' => false,
                'message' => $check['error'] ?? 'Aucune mise à jour disponible.',
                'output' => '',
            ];
        }

        $fromVersion = $this->installedVersion->current();
        $toVersion = (string) ($check['latest'] ?? $fromVersion);
        $panelRoot = base_path();
        $updateScript = $panelRoot.'/install/update-panel.sh';

        $history = UpdateHistory::query()->create([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'status' => 'running',
            'changelog_url' => $check['changelog_url'] ?? null,
        ]);

        try {
            $result = $this->executor->run(
                'sudo -n '.escapeshellarg($updateScript),
                ['timeout' => 900],
            );

            $output = trim($result->output()."\n".$result->errorOutput());

            if (! $result->successful()) {
                $history->update([
                    'status' => 'failed',
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                Log::warning('Panel update failed', ['output' => $output]);

                return [
                    'success' => false,
                    'message' => 'Échec de la mise à jour. Vérifiez les droits sudo (réinstallez ou exécutez update-panel.sh en SSH).',
                    'output' => $output,
                ];
            }

            $history->update([
                'status' => 'completed',
                'output' => $output,
                'completed_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => "Mise à jour vers v{$toVersion} terminée. Rechargez la page (Ctrl+F5).",
                'output' => $output,
            ];
        } catch (Throwable $exception) {
            Log::error('Panel update exception', ['message' => $exception->getMessage()]);

            $history->update([
                'status' => 'failed',
                'output' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur interne : '.$exception->getMessage(),
                'output' => '',
            ];
        }
    }

    public function canUpdate(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $panelRoot = base_path();

        return File::isDirectory($panelRoot.'/.git')
            && is_file($panelRoot.'/install/update-panel.sh');
    }
}
