<?php

declare(strict_types=1);

namespace App\Listeners\CrashAnalyzer;

use App\Events\CrashAnalyzer\CrashDetected;
use App\Events\CrashAnalyzer\UnexpectedRebootDetected;
use App\Jobs\CrashAnalyzer\SendCrashNotificationJob;
use App\Services\Monitoring\MonitoringAlertService;

final class DispatchCrashNotifications
{
    public function __construct(
        private readonly MonitoringAlertService $alerts,
    ) {}

    public function handleCrash(CrashDetected $event): void
    {
        $this->alerts->recordCrashEvent($event->server, $event->event);
        SendCrashNotificationJob::dispatch($event->server->id, $event->event->id);
    }

    public function handleReboot(UnexpectedRebootDetected $event): void
    {
        $this->alerts->recordCrashEvent($event->server, $event->event);
        SendCrashNotificationJob::dispatch($event->server->id, $event->event->id);
    }
}
