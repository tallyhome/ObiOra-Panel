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
    public function recoverStale(int $maxAgeMinutes = 40): int
    {
        $cutoff = now()->subMinutes($maxAgeMinutes);

        $stale = UpdateHistory::query()
            ->whereIn('status', ['queued', 'running'])
            ->where(function ($query) use ($cutoff): void {
                $query->where('updated_at', '<', $cutoff)
                    ->orWhere('created_at', '<', $cutoff);
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

        return $stale->count();
    }
}
