<?php

declare(strict_types=1);

namespace App\Jobs\Monitoring;

use App\Services\Monitoring\MonitorAgentDeployProgressService;
use App\Services\Monitoring\MonitorAgentDeployRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class MonitorAgentRemoteDeployJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly int $serverId,
        public readonly string $sshHost,
        public readonly int $sshPort,
        public readonly string $sshUser,
    ) {}

    public function handle(MonitorAgentDeployRunner $runner): void
    {
        $runner->run($this->serverId, $this->sshHost, $this->sshPort, $this->sshUser);
    }

    public function failed(\Throwable $exception): void
    {
        app(MonitorAgentDeployProgressService::class)->finish(
            $this->serverId,
            false,
            'Erreur interne : '.$exception->getMessage(),
            $exception->getMessage(),
        );
    }
}
