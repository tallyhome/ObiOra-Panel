<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\MonitorRunnerService;
use Illuminate\Console\Command;

final class RunMonitorsCommand extends Command
{
    protected $signature = 'obiora:run-monitors';

    protected $description = 'Lance les sondes moniteurs externes dont l\'intervalle est échu';

    public function handle(MonitorRunnerService $runner): int
    {
        $count = $runner->dispatchDueChecks();

        $this->info("{$count} sonde(s) mise(s) en file d'attente.");

        return self::SUCCESS;
    }
}
