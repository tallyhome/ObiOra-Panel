<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Server;
use App\Services\System\ServiceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ServiceActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $serverId,
        public readonly string $service,
        public readonly string $action,
    ) {}

    public function handle(ServiceManager $serviceManager): void
    {
        $server = Server::query()->find($this->serverId);

        if ($server === null) {
            return;
        }

        $serviceManager->runActionSync($this->service, $this->action, $server);
    }
}
