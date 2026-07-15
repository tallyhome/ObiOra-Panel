<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Core\MasterServerSync;
use Illuminate\Console\Command;

final class SyncMasterServerCommand extends Command
{
    protected $signature = 'obiora:sync-master';

    protected $description = 'Crée ou répare le serveur maître local, synchronise agent.json et redémarre obiora-agent';

    public function handle(MasterServerSync $sync): int
    {
        $server = $sync->reconcile();

        $this->info("Serveur maître : {$server->name} (#{$server->id}) — {$server->ip_address}");
        $this->line('agent.json resynchronisé et obiora-agent redémarré si nécessaire.');

        return self::SUCCESS;
    }
}
