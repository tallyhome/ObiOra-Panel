<?php

declare(strict_types=1);

namespace App\Jobs\CrashAnalyzer;

use App\Models\CrashAnalyzerReport;
use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessCrashReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $serverId,
        public readonly int $reportId,
    ) {}

    public function handle(CrashAnalyzerNotificationService $notifications): void
    {
        $server = Server::query()->find($this->serverId);
        $report = CrashAnalyzerReport::query()->find($this->reportId);

        if ($server === null || $report === null) {
            return;
        }

        $notifications->notifyReport($server, $report);
    }
}
