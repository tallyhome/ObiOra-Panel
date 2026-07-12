<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MonitorCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneOldMonitorChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $days = max(7, (int) config('obiora.monitoring.check_retention_days', 30));
        $cutoff = now()->subDays($days);

        MonitorCheck::query()
            ->where('checked_at', '<', $cutoff)
            ->delete();
    }
}
