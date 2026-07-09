<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use App\Jobs\ApplyPanelUpdateJob;
use App\Models\UpdateHistory;
use App\Support\InstalledVersion;
use Illuminate\Support\Facades\Artisan;
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

        $this->ensureQueueWorkerRunning();

        $fromVersion = $this->installedVersion->current();
        $toVersion = (string) ($check['latest'] ?? $fromVersion);

        $history = UpdateHistory::query()->create([
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'status' => 'queued',
            'progress' => 2,
            'progress_message' => 'Mise en file d\'attente…',
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

        $history->update([
            'status' => 'running',
            'progress' => 5,
            'progress_message' => 'Démarrage de la mise à jour…',
        ]);

        $panelRoot = base_path();
        $updateScript = $panelRoot.'/install/update-panel.sh';
        $helper = '/usr/local/bin/obiora-panel-update';
        $targetVersion = ltrim((string) $history->to_version, 'v');

        if (! $this->ensureUpdateScriptReady($panelRoot)) {
            $history->update([
                'status' => 'failed',
                'progress' => 100,
                'progress_message' => 'Échec de la mise à jour',
                'output' => 'Script install/update-panel.sh absent ou illisible. Vérifiez les permissions du dépôt git.',
                'completed_at' => now(),
            ]);

            return;
        }

        try {
            // IMPORTANT : on fusionne stdout+stderr avec "2>&1" au niveau shell
            // (et non en concaténant $result->output puis $result->errorOutput
            // après coup), pour conserver l'ordre chronologique réel des lignes.
            // Sans ça, l'affichage "fin du log" peut montrer un warning npm/vite
            // anodin (écrit sur stderr en fin d'exécution) alors que la vraie
            // erreur (ex. commande artisan en échec) est en réalité plus tôt
            // dans le flux stdout et se retrouve masquée.
            $versionArg = escapeshellarg($targetVersion);

            if ($this->setuidHelperAvailable($helper)) {
                // Binaire ELF setuid root — ne dépend pas de sudoers
                $result = $this->executor->run(
                    escapeshellarg($helper).' '.escapeshellarg((string) $historyId).' '.$versionArg.' 2>&1',
                    ['timeout' => 1500],
                );

                $output = trim($result->output !== '' ? $result->output : $result->errorOutput);

                if (! $result->successful && str_contains($output, 'script de mise à jour introuvable')) {
                    Log::warning('Helper MAJ obsolète ou script sans +x — fallback sudo bash', ['output' => $output]);
                    $result = $this->executor->run(
                        'sudo -n /bin/bash '.escapeshellarg($updateScript).' '.escapeshellarg((string) $historyId).' '.$versionArg.' 2>&1',
                        ['timeout' => 1500],
                    );
                }
            } else {
                // Fallback sudo (obiora-queue tourne sous l'utilisateur obiora)
                $result = $this->executor->run(
                    'sudo -n /bin/bash '.escapeshellarg($updateScript).' '.escapeshellarg((string) $historyId).' '.$versionArg.' 2>&1',
                    ['timeout' => 1500],
                );
            }

            $output = trim($result->output !== '' ? $result->output : $result->errorOutput);

            if (! $result->successful) {
                $history->update([
                    'status' => 'failed',
                    'progress' => 100,
                    'progress_message' => 'Échec de la mise à jour',
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                Log::warning('Panel update failed', ['output' => $output]);
                $this->finalizePanelHttp();

                return;
            }

            $history->update([
                'status' => 'completed',
                'progress' => 100,
                'progress_message' => 'Mise à jour terminée',
                'output' => $output,
                'completed_at' => now(),
            ]);

            $this->finalizePanelHttp();
            $this->restartQueueWorkerDeferred();
        } catch (Throwable $exception) {
            Log::error('Panel update exception', ['message' => $exception->getMessage()]);

            $history->update([
                'status' => 'failed',
                'progress' => 100,
                'progress_message' => 'Erreur interne',
                'output' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $this->finalizePanelHttp();
        }
    }

    public function canUpdate(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $panelRoot = base_path();

        return File::isDirectory($panelRoot.'/.git')
            && is_readable($panelRoot.'/install/update-panel.sh');
    }

    /**
     * Restaure update-panel.sh depuis git si absent et garantit qu'il est lisible/exécutable.
     */
    private function ensureUpdateScriptReady(string $panelRoot): bool
    {
        $updateScript = $panelRoot.'/install/update-panel.sh';

        if (! is_file($updateScript) && File::isDirectory($panelRoot.'/.git')) {
            try {
                $this->executor->run(
                    'git -C '.escapeshellarg($panelRoot).' checkout HEAD -- install/update-panel.sh install/lib/panel-update-helper.c install/lib/panel-update-helper.sh 2>&1',
                    ['timeout' => 120],
                );
            } catch (Throwable $exception) {
                Log::warning('Impossible de restaurer update-panel.sh depuis git', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (! is_file($updateScript)) {
            return false;
        }

        if (! is_executable($updateScript)) {
            @chmod($updateScript, 0755);

            try {
                $this->executor->run('chmod +x '.escapeshellarg($updateScript).' 2>&1', ['timeout' => 10]);
            } catch (Throwable) {
                // bash peut lancer un script lisible sans +x
            }
        }

        return is_readable($updateScript);
    }

    /**
     * Le setuid Linux ne fonctionne que sur les binaires ELF, pas sur les scripts bash.
     */
    private function setuidHelperAvailable(string $helper): bool
    {
        if (! is_executable($helper)) {
            return false;
        }

        $stat = @stat($helper);
        if ($stat === false || ($stat['mode'] & 0o4000) === 0) {
            return false;
        }

        $handle = @fopen($helper, 'rb');
        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        return $magic === "\x7FELF";
    }

    /**
     * S'assure que le worker de file d'attente tourne avant de lui confier la
     * MAJ, pour que le client n'ait jamais besoin de lancer une commande SSH.
     * Best-effort : si le service n'existe pas ou que sudo échoue, le job
     * reste simplement "en file d'attente" (visible dans l'UI) sans bloquer.
     */
    private function ensureQueueWorkerRunning(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        try {
            $status = $this->executor->run('sudo -n systemctl is-active obiora-queue', ['timeout' => 10]);

            if (trim($status->output) === 'active') {
                return;
            }

            $this->executor->run('sudo -n systemctl start obiora-queue', ['timeout' => 15]);
        } catch (Throwable $exception) {
            Log::info('Impossible de vérifier/démarrer obiora-queue automatiquement', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function restartQueueWorkerDeferred(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        try {
            $this->executor->run(
                'sudo -n systemctl restart obiora-queue',
                ['timeout' => 30],
            );
        } catch (Throwable $exception) {
            Log::info('Redémarrage obiora-queue après MAJ ignoré', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Rétablit le panel HTTP après MAJ (évite 502 Bad Gateway / mode maintenance bloqué).
     */
    private function finalizePanelHttp(): void
    {
        try {
            Artisan::call('up');
            Artisan::call('optimize:clear');
        } catch (Throwable $exception) {
            Log::warning('Récupération artisan post-MAJ partielle', ['message' => $exception->getMessage()]);
        }

        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        $script = base_path('install/lib/update-recover.sh');

        if (! is_file($script)) {
            return;
        }

        try {
            $this->executor->run(
                'sudo -n /bin/bash '.escapeshellarg($script).' 2>&1',
                ['timeout' => 120],
            );
        } catch (Throwable $exception) {
            Log::warning('Récupération HTTP post-MAJ (systemd) ignorée', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
