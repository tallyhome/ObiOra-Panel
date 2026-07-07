<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\MonitoringAlertService;
use Illuminate\Console\Command;

final class SendMonitoringAlertsCommand extends Command
{
    protected $signature = 'obiora:monitor-alerts';

    protected $description = 'Envoie les alertes monitoring par email';

    public function handle(MonitoringAlertService $alerts): int
    {
        $sent = $alerts->dispatchPendingEmailAlerts();
        $this->info("Alertes envoyees : {$sent}");

        return self::SUCCESS;
    }
}
