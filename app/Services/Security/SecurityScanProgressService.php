<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;

final class SecurityScanProgressService
{
    private const TTL_SECONDS = 900;

    public function start(int $serverId): void
    {
        Cache::put($this->key($serverId), [
            'status' => 'running',
            'progress' => 5,
            'message' => 'Préparation du scan sécurité…',
            'output' => '',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
        ], self::TTL_SECONDS);
    }

    public function update(int $serverId, int $progress, string $message, ?string $output = null): void
    {
        $current = $this->get($serverId) ?? [];
        Cache::put($this->key($serverId), [
            'status' => 'running',
            'progress' => min(99, max(0, $progress)),
            'message' => $message,
            'output' => $output ?? ($current['output'] ?? ''),
            'started_at' => $current['started_at'] ?? now()->toIso8601String(),
            'finished_at' => null,
        ], self::TTL_SECONDS);
    }

    /**
     * @param  array{success: bool, message: string, output: string}  $result
     */
    public function finish(int $serverId, array $result): void
    {
        Cache::put($this->key($serverId), [
            'status' => $result['success'] ? 'completed' : 'failed',
            'progress' => 100,
            'message' => $result['message'],
            'output' => $result['output'] ?? '',
            'started_at' => ($this->get($serverId) ?? [])['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $serverId): ?array
    {
        $state = Cache::get($this->key($serverId));

        return is_array($state) ? $state : null;
    }

    public function clear(int $serverId): void
    {
        Cache::forget($this->key($serverId));
    }

    private function key(int $serverId): string
    {
        return 'security_scan_progress:'.$serverId;
    }
}
