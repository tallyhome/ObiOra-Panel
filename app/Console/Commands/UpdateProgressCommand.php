<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UpdateHistory;
use Illuminate\Console\Command;

final class UpdateProgressCommand extends Command
{
    protected $signature = 'obiora:update-progress {historyId} {progress} {message?}';

    protected $description = 'Met à jour la progression d\'une mise à jour panel en cours';

    public function handle(): int
    {
        $historyId = (int) $this->argument('historyId');
        $progress = min(100, max(0, (int) $this->argument('progress')));
        $message = (string) ($this->argument('message') ?? '');

        $history = UpdateHistory::query()->find($historyId);

        if ($history === null) {
            return self::FAILURE;
        }

        $history->update([
            'progress' => $progress,
            'progress_message' => $message !== '' ? $message : $history->progress_message,
        ]);

        return self::SUCCESS;
    }
}
