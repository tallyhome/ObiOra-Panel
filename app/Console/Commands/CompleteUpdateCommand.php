<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UpdateHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CompleteUpdateCommand extends Command
{
    protected $signature = 'obiora:update-complete {historyId} {status=completed : completed|failed} {--message=}';

    protected $description = 'Clôture une entrée update_history (appelé par update-panel.sh)';

    public function handle(): int
    {
        $historyId = (int) $this->argument('historyId');
        $status = (string) $this->argument('status');

        if (! in_array($status, ['completed', 'failed'], true)) {
            $this->error('Statut invalide (completed|failed).');

            return self::FAILURE;
        }

        $history = UpdateHistory::query()->find($historyId);

        if ($history === null) {
            return self::FAILURE;
        }

        $message = (string) ($this->option('message') ?? '');
        if ($message === '') {
            $message = $status === 'completed'
                ? 'Mise à jour terminée'
                : 'Échec de la mise à jour';
        }

        $history->update([
            'status' => $status,
            'progress' => 100,
            'progress_message' => $message,
            'completed_at' => now(),
        ]);

        $lock = storage_path('framework/obiora-update.lock');
        if ($status === 'completed' && File::exists($lock)) {
            try {
                File::delete($lock);
            } catch (\Throwable) {
                // best-effort
            }
        }

        return self::SUCCESS;
    }
}
