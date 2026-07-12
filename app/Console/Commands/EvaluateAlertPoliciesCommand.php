<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\AlertPolicyEvaluator;
use Illuminate\Console\Command;

final class EvaluateAlertPoliciesCommand extends Command
{
    protected $signature = 'obiora:evaluate-alert-policies';

    protected $description = 'Évalue les politiques d\'alerte et ouvre/résout les incidents';

    public function handle(AlertPolicyEvaluator $evaluator): int
    {
        $stats = $evaluator->evaluateAll();

        $this->info(sprintf(
            'Évalués: %d — ouverts: %d — résolus: %d — notifications: %d',
            $stats['evaluated'],
            $stats['opened'],
            $stats['resolved'],
            $stats['notified'],
        ));

        return self::SUCCESS;
    }
}
