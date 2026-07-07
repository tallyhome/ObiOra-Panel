<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Core\PanelUpdater;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
}
