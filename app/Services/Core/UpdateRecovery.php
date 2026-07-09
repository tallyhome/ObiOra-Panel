<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\UpdateHistory;
use Illuminate\Support\Facades\Log;

final class UpdateRecovery
{
    /**
     * Marque comme échouées les MAJ bloquées (worker tué, timeout, cache incohérent).
     */
    public function recoverStale(int $maxAgeMinutes = 20): int
    {
        if ($maxAgeMinutes <= 0) {
            return 0;
        }

        $cutoff = now()->subMinutes($maxAgeMinutes);
        $stuckProgressCutoff = now()->subMinutes(min(25, max(10, (int) ($maxAgeMinutes * 0.75))));

        $stale = UpdateHistory::query()
            ->whereIn('status', ['queued', 'running'])
            ->where(function ($query) use ($cutoff, $stuckProgressCutoff): void {
                $query->where('updated_at', '<', $cutoff)
                    ->orWhere('created_at', '<', $cutoff)
                    ->orWhere(function ($inner) use ($stuckProgressCutoff): void {
                        $inner->where('status', 'running')
                            ->where('updated_at', '<', $stuckProgressCutoff);
                    })
                    ->orWhere(function ($inner) use ($stuckProgressCutoff): void {
                        $inner->where('status', 'queued')
                            ->where('created_at', '<', $stuckProgressCutoff);
                    });
            })
            ->get();

        foreach ($stale as $history) {
            Log::warning('Recovery MAJ panel — entrée stale marquée failed', [
                'id' => $history->id,
                'from' => $history->from_version,
                'to' => $history->to_version,
                'progress' => $history->progress,
            ]);

            $history->update([
                'status' => 'failed',
                'progress' => max((int) $history->progress, 5),
                'progress_message' => 'Mise à jour interrompue (timeout ou worker arrêté). Relancez depuis le panel ou en SSH.',
                'output' => trim((string) $history->output."\n\n[recovery] Entrée stale auto-clôturée après {$maxAgeMinutes} min."),
                'completed_at' => now(),
            ]);
        }

        if ($stale->isNotEmpty()) {
            $this->recoverPanelHttp();
        }

        return $stale->count();
    }

    private function recoverPanelHttp(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('obiora:recover-panel-http', ['--skip-systemd' => true]);
        } catch (\Throwable) {
            //
        }

        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        $script = base_path('install/lib/update-recover.sh');

        if (! is_file($script)) {
            return;
        }

        try {
            app(\App\Contracts\SystemExecutorInterface::class)->run(
                'sudo -n /bin/bash '.escapeshellarg($script).' 2>&1',
                ['timeout' => 120],
            );
        } catch (\Throwable) {
            //
        }
    }
}
