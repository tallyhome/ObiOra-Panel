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
        $server = $sync->ensure();

        $this->info("Serveur maître : {$server->name} (#{$server->id}) — {$server->ip_address}");

        if (PHP_OS_FAMILY === 'Linux') {
            @shell_exec('sudo -n systemctl restart obiora-agent 2>/dev/null');
            @shell_exec('sudo -n systemctl restart obiora-queue 2>/dev/null');
            $this->line('Services obiora-agent / obiora-queue redémarrés (best-effort).');
        }

        return self::SUCCESS;
    }
}
