<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ServerPingSample;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneOldServerPingSamplesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $days = max(7, (int) config('monitoring.retention_days', 60));
        $cutoff = now()->subDays($days);

        ServerPingSample::query()
            ->where('sampled_at', '<', $cutoff)
            ->delete();
    }
}
