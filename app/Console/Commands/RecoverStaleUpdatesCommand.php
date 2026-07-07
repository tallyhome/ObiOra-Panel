<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Core\UpdateRecovery;
use Illuminate\Console\Command;

final class RecoverStaleUpdatesCommand extends Command
{
    protected $signature = 'obiora:recover-updates {--minutes=40 : Age minimum en minutes}';

    protected $description = 'Marque comme échouées les mises à jour panel bloquées en queued/running';

    public function handle(UpdateRecovery $recovery): int
    {
        $count = $recovery->recoverStale((int) $this->option('minutes'));

        if ($count === 0) {
            $this->info('Aucune mise à jour stale.');

            return self::SUCCESS;
        }

        $this->warn("{$count} mise(s) à jour marquée(s) failed.");

        return self::SUCCESS;
    }
}
