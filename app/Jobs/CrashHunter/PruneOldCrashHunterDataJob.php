<?php

declare(strict_types=1);

namespace App\Jobs\CrashHunter;

use App\Models\Server;
use App\Services\CrashHunter\CrashHunterMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneOldCrashHunterDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(CrashHunterMetricsService $metrics): void
    {
        $retentionHours = (int) config('crash_hunter.snapshot_retention_hours', 24);

        Server::query()->each(function (Server $server) use ($metrics, $retentionHours) {
            $metrics->pruneOld($server, $retentionHours);
        });
    }
}
