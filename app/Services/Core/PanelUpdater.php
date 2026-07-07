<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use App\Jobs\ApplyPanelUpdateJob;
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
     * Crée l'entrée d'historique et met la mise à jour en file d'attente
     * (exécutée par le worker `obiora-queue`, hors du cycle de requête HTTP).
     *
     * @return array{success: bool, message: string, history_id: ?int}
     */
    public function queueUpdate(): array
    {
        if (! $this->canUpdate()) {
            return [
                'success' => false,
                'message' => 'Mise à jour indisponible : dépôt git introuvable ou script update-panel.sh absent.',
                'history_id' => null,
            ];
        }

        $check = $this->updateManager->checkForUpdates(fresh: true);

        if (! ($check['available'] ?? false)) {
            return [
                'success' => false,
                'message' => $check['error'] ?? 'Aucune mise à jour disponible.',
                'history_id' => null,
            ];
        }

        $fromVersion = $this->installedVersion->current();
        $toVersion = (string) ($check['latest'] ?? $fromVersion);

        $history = UpdateHistory::query()->create([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'status' => 'queued',
            'changelog_url' => $check['changelog_url'] ?? null,
        ]);

        try {
            ApplyPanelUpdateJob::dispatch($history->id);
        } catch (Throwable $exception) {
            Log::error('Impossible de mettre la MAJ en file d\'attente', ['message' => $exception->getMessage()]);

            $history->update([
                'status' => 'failed',
                'output' => 'Échec de mise en file d\'attente : '.$exception->getMessage(),
                'completed_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => 'Impossible de lancer la mise à jour (file d\'attente indisponible). Vérifiez que le service obiora-queue est actif.',
                'history_id' => $history->id,
            ];
        }

        return [
            'success' => true,
            'message' => "Mise à jour vers v{$toVersion} lancée en arrière-plan. Cela peut prendre plusieurs minutes.",
            'history_id' => $history->id,
        ];
    }

    /**
     * Exécute réellement le script de mise à jour. Appelé par le job en file d'attente,
     * donc sans limite de temps liée à une requête HTTP.
     */
    public function runQueuedUpdate(int $historyId): void
    {
        $history = UpdateHistory::query()->find($historyId);

        if ($history === null) {
            Log::error('UpdateHistory introuvable pour la MAJ en file d\'attente', ['id' => $historyId]);

            return;
        }

        $history->update(['status' => 'running']);

        $panelRoot = base_path();
        $updateScript = $panelRoot.'/install/update-panel.sh';

        try {
            $result = $this->executor->run(
                'sudo -n '.escapeshellarg($updateScript),
                ['timeout' => 1500],
            );

            $output = trim($result->output."\n".$result->errorOutput);

            if (! $result->successful) {
                $history->update([
                    'status' => 'failed',
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                Log::warning('Panel update failed', ['output' => $output]);

                return;
            }

            $history->update([
                'status' => 'completed',
                'output' => $output,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Panel update exception', ['message' => $exception->getMessage()]);

            $history->update([
                'status' => 'failed',
                'output' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
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
