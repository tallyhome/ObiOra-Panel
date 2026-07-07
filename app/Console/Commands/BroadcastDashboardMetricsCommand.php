<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\Realtime\RealtimeBroadcaster;
use Illuminate\Console\Command;

final class BroadcastDashboardMetricsCommand extends Command
{
    protected $signature = 'obiora:broadcast-metrics {--server= : ID serveur specifique}';

    protected $description = 'Diffuse les metriques dashboard via Reverb (Phase 11)';

    public function handle(RealtimeBroadcaster $broadcaster): int
    {
        if (! $broadcaster->isEnabled()) {
            return self::SUCCESS;
        }

        $query = Server::query()->orderBy('id');
        if ($this->option('server')) {
            $query->whereKey((int) $this->option('server'));
        }

        foreach ($query->get() as $server) {
            $broadcaster->dashboard($server);
        }

        return self::SUCCESS;
    }
}
