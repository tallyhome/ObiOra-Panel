<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class ObioraProgressCommand extends Command
{
    protected $signature = 'obiora:progress {key} {progress} {message?}';

    protected $description = 'Met à jour une progression de tâche longue (cache)';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $progress = min(100, max(0, (int) $this->argument('progress')));
        $message = (string) ($this->argument('message') ?? '');

        Cache::put("obiora_progress:{$key}", [
            'progress' => $progress,
            'message' => $message,
            'running' => $progress < 100,
            'success' => null,
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        return self::SUCCESS;
    }
}
