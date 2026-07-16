<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\Realtime\RealtimeBroadcaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BroadcastDashboardMetricsCommand extends Command
{
    protected $signature = 'obiora:broadcast-metrics {--server= : ID serveur specifique}';

    protected $description = 'Diffuse les metriques dashboard via Reverb (Phase 11)';

    public function handle(RealtimeBroadcaster $broadcaster): int
    {
        if (! $broadcaster->isEnabled()) {
            return self::SUCCESS;
        }

        try {
            $query = Server::query()->orderBy('id');
            if ($this->option('server')) {
                $query->whereKey((int) $this->option('server'));
            }

            foreach ($query->get() as $server) {
                $broadcaster->dashboard($server);
            }
        } catch (Throwable $e) {
            // Ne pas faire échouer le scheduler (MariaDB/Reverb down overnight).
            Log::warning('obiora:broadcast-metrics skipped', ['message' => $e->getMessage()]);

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
