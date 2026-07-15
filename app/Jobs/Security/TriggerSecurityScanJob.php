<?php

declare(strict_types=1);

namespace App\Jobs\Security;

use App\Models\Server;
use App\Services\Security\SecurityScanTriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class TriggerSecurityScanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;

    public function __construct(
        public int $serverId,
    ) {}

    public function handle(SecurityScanTriggerService $trigger): void
    {
        $server = Server::query()->find($this->serverId);

        if ($server === null) {
            return;
        }

        $result = $trigger->trigger($server);

        if (! $result['success']) {
            Log::warning('Security scan job failed', [
                'server_id' => $this->serverId,
                'message' => $result['message'],
            ]);
        }
    }
}
