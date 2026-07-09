<?php

declare(strict_types=1);

namespace App\Jobs\Servers;

use App\Services\Servers\SlaveDeployProgressService;
use App\Services\Servers\SlaveDeployRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SlaveRemoteDeployJob implements ShouldQueue
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

    public function handle(SlaveDeployRunner $runner): void
    {
        $runner->run($this->serverId, $this->sshHost, $this->sshPort, $this->sshUser);
    }

    public function failed(\Throwable $exception): void
    {
        app(SlaveDeployProgressService::class)->finish(
            $this->serverId,
            false,
            'Erreur interne : '.$exception->getMessage(),
            $exception->getMessage(),
        );
    }
}
