<?php

declare(strict_types=1);

namespace App\Services\Deploy;

use App\Models\DeployLog;
use Illuminate\Support\Facades\Log;

final class DeployLogService
{
    public function log(
        ?int $serverId,
        string $deployType,
        string $message,
        string $level = 'info',
        array $meta = [],
    ): void {
        $message = trim($message);

        if ($message === '') {
            return;
        }

        try {
            DeployLog::query()->create([
                'server_id' => $serverId,
                'user_id' => auth()->id(),
                'deploy_type' => $deployType,
                'level' => $level,
                'message' => $message,
                'meta' => $meta !== [] ? $meta : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Impossible d\'enregistrer deploy_log', ['error' => $e->getMessage()]);
        }

        Log::channel('deploy')->log($level, "[{$deployType}] {$message}", array_filter([
            'server_id' => $serverId,
            'user_id' => auth()->id(),
            ...$meta,
        ]));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DeployLog>
     */
    public function recentForServer(?int $serverId, string $deployType, int $limit = 80)
    {
        if ($serverId === null) {
            return collect();
        }

        return DeployLog::query()
            ->where('server_id', $serverId)
            ->where('deploy_type', $deployType)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
