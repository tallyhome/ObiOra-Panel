<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\UpdateHistory;
use App\Services\Core\PanelUpdater;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ApplyPanelUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $historyId,
    ) {}

    public function handle(PanelUpdater $panelUpdater): void
    {
        $panelUpdater->runQueuedUpdate($this->historyId);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?? 'Job interrompu (timeout ou worker arrêté).';

        UpdateHistory::query()
            ->where('id', $this->historyId)
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'failed',
                'progress' => 100,
                'progress_message' => 'Échec de la mise à jour — récupération HTTP…',
                'output' => $message,
                'completed_at' => now(),
            ]);

        // Timeout / worker tué : le shell EXIT peut ne pas tourner — forcer update-recover.
        try {
            app(PanelUpdater::class)->finalizePanelHttp();
        } catch (Throwable $recoverException) {
            report($recoverException);
        }
    }
}
