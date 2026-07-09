<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ObioraQueueService
{
    public function __construct(
        private readonly SystemExecutorInterface $executor,
    ) {}

    public function isWorkerActive(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return config('queue.default') === 'sync';
        }

        try {
            $status = $this->executor->run('sudo -n systemctl is-active obiora-queue', ['timeout' => 10]);

            return trim($status->output) === 'active';
        } catch (Throwable) {
            return false;
        }
    }

    public function ensureWorkerRunning(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return true;
        }

        if ($this->isWorkerActive()) {
            return true;
        }

        try {
            $this->executor->run('sudo -n systemctl start obiora-queue', ['timeout' => 15]);

            return $this->isWorkerActive();
        } catch (Throwable $exception) {
            Log::info('Impossible de démarrer obiora-queue', ['message' => $exception->getMessage()]);

            return false;
        }
    }
}
