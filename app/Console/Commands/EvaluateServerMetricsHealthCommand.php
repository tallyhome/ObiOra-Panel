<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\ServerMetricsHealthService;
use Illuminate\Console\Command;

final class EvaluateServerMetricsHealthCommand extends Command
{
    protected $signature = 'obiora:evaluate-server-metrics';

    protected $description = 'Marque les serveurs dégradés/offline selon la dernière métrique agent';

    public function handle(ServerMetricsHealthService $health): int
    {
        $stats = $health->evaluateAll();
        $this->info(sprintf(
            'Évalués: %d — dégradés: %d — offline: %d',
            $stats['evaluated'],
            $stats['degraded'],
            $stats['offline'],
        ));

        return self::SUCCESS;
    }
}
