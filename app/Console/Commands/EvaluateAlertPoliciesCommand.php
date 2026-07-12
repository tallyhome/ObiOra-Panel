<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\AlertPolicyEvaluator;
use App\Services\Monitoring\MonitoringAlertIntelligenceService;
use Illuminate\Console\Command;

final class EvaluateAlertPoliciesCommand extends Command
{
    protected $signature = 'obiora:evaluate-alert-policies';

    protected $description = 'Évalue les politiques d\'alerte et ouvre/résout les incidents';

    public function handle(AlertPolicyEvaluator $evaluator, MonitoringAlertIntelligenceService $intelligence): int
    {
        $stats = $evaluator->evaluateAll();
        $intel = $intelligence->run();

        $this->info(sprintf(
            'Évalués: %d — ouverts: %d — résolus: %d — notifications: %d — fusionnés: %d — escaladés: %d',
            $stats['evaluated'],
            $stats['opened'],
            $stats['resolved'],
            $stats['notified'],
            $intel['merged'],
            $intel['escalated'],
        ));

        return self::SUCCESS;
    }
}
