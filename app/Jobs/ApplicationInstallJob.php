<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Applications\ApplicationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ApplicationInstallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(
        public readonly int $applicationId,
        public readonly string $slug,
        public readonly int $serverId,
        public readonly string $action = 'install',
    ) {}

    public function handle(ApplicationManager $manager): void
    {
        if ($this->action === 'uninstall') {
            $manager->runUninstallJob($this->applicationId);

            return;
        }

        $manager->runInstallJob($this->applicationId, $this->slug, $this->serverId);
    }
}
