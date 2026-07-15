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

    public function panelVersion(): string
    {
        $path = base_path('VERSION');

        return is_readable($path) ? trim((string) file_get_contents($path)) : '';
    }

    public function workerVersionMarkerPath(): string
    {
        return storage_path('framework/obiora-queue.version');
    }

    /**
     * Recharge obiora-queue si le worker tourne encore avec une ancienne version du panel.
     * Les workers Laravel gardent le code PHP en mémoire jusqu'au restart systemd.
     */
    public function ensureFreshWorker(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return true;
        }

        $current = $this->panelVersion();
        $marker = is_file($this->workerVersionMarkerPath())
            ? trim((string) file_get_contents($this->workerVersionMarkerPath()))
            : '';

        if ($current !== '' && $current === $marker && $this->isWorkerActive()) {
            return true;
        }

        return $this->reloadWorker($current);
    }

    public function markWorkerVersionLoaded(?string $version = null): void
    {
        $version = $version ?? $this->panelVersion();

        if ($version === '') {
            return;
        }

        @file_put_contents($this->workerVersionMarkerPath(), $version);
    }

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
        return $this->ensureFreshWorker();
    }

    private function reloadWorker(string $version): bool
    {
        try {
            if ($this->isWorkerActive()) {
                $this->executor->run('sudo -n systemctl restart obiora-queue', ['timeout' => 30]);
                usleep(1_500_000);
            } else {
                $this->executor->run('sudo -n systemctl start obiora-queue', ['timeout' => 15]);
            }

            $this->markWorkerVersionLoaded($version);

            return $this->isWorkerActive();
        } catch (Throwable $exception) {
            Log::warning('Impossible de recharger obiora-queue', [
                'message' => $exception->getMessage(),
                'target_version' => $version,
            ]);

            return false;
        }
    }
}
