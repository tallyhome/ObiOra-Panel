<?php

declare(strict_types=1);

namespace App\Jobs\CrashAnalyzer;

use App\Models\CrashAnalyzerEvent;
use App\Models\Server;
use App\Services\CrashAnalyzer\CrashAnalyzerNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendCrashNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $serverId,
        public readonly int $eventId,
    ) {}

    public function handle(CrashAnalyzerNotificationService $notifications): void
    {
        $server = Server::query()->find($this->serverId);
        $event = CrashAnalyzerEvent::query()->find($this->eventId);

        if ($server === null || $event === null || $event->notified) {
            return;
        }

        $notifications->notifyCrash($server, $event);
    }
}
