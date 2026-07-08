<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Demo\DemoAccountService;
use Illuminate\Console\Command;

final class ExpireDemoAccountsCommand extends Command
{
    protected $signature = 'obiora:expire-demo-accounts';

    protected $description = 'Supprime les comptes démo ObiOra Panel expirés';

    public function handle(DemoAccountService $service): int
    {
        $count = $service->expireDue();
        $this->info("{$count} compte(s) démo supprimé(s).");

        return self::SUCCESS;
    }
}
