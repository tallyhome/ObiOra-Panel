<?php

declare(strict_types=1);

namespace App\Jobs\CrashAnalyzer;

use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneOldMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(CrashAnalyzerMetricsService $metrics): void
    {
        $retentionHours = (int) config('crash_analyzer.retention_hours', 72);

        Server::query()->each(function (Server $server) use ($metrics, $retentionHours) {
            $metrics->pruneOld($server, $retentionHours);
        });
    }
}
