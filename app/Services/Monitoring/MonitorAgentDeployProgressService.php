<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Services\Deploy\DeployLogService;
use Illuminate\Support\Facades\Cache;

final class MonitorAgentDeployProgressService
{
    private const MAX_CONSOLE_LINES = 500;

    public function cacheKey(int $serverId): string
    {
        return 'monitor_agent_deploy:'.$serverId;
    }

    public function start(int $serverId): void
    {
        $this->store($serverId, [
            'progress' => 5,
            'message' => 'Préparation de l\'installation agent monitor…',
            'running' => true,
            'console' => ['['.now()->format('H:i:s').'] Installation agent monitor démarrée…'],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function appendLog(int $serverId, string $line): void
    {
        $current = $this->status($serverId) ?? [
            'progress' => 5,
            'message' => '',
            'running' => true,
            'console' => [],
        ];

        /** @var list<string> $console */
        $console = is_array($current['console'] ?? null) ? $current['console'] : [];
        $console[] = '['.now()->format('H:i:s').'] '.$line;

        if (count($console) > self::MAX_CONSOLE_LINES) {
            $console = array_slice($console, -self::MAX_CONSOLE_LINES);
        }

        $current['console'] = $console;
        $current['updated_at'] = now()->toIso8601String();

        $this->store($serverId, $current);

        $level = str_starts_with(strtoupper($line), 'ERREUR') ? 'error' : 'info';
        app(DeployLogService::class)->log($serverId, 'monitor_agent', $line, $level);
    }

    public function update(int $serverId, int $progress, string $message): void
    {
        $current = $this->status($serverId) ?? [];

        $this->store($serverId, [
            'progress' => $progress,
            'message' => $message,
            'running' => true,
            'console' => $current['console'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function finish(int $serverId, bool $success, string $message, string $log = ''): void
    {
        $current = $this->status($serverId) ?? [];

        $this->store($serverId, [
            'progress' => 100,
            'message' => $message,
            'running' => false,
            'success' => $success,
            'log' => $log !== '' ? $log : $message,
            'console' => $current['console'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ]);

        app(DeployLogService::class)->log(
            $serverId,
            'monitor_agent',
            $message,
            $success ? 'info' : 'error',
        );
    }

    public function cancel(int $serverId, string $reason = 'Installation annulée.'): void
    {
        $this->appendLog($serverId, $reason);
        $current = $this->status($serverId) ?? [];

        $this->store($serverId, [
            'progress' => (int) ($current['progress'] ?? 0),
            'message' => $reason,
            'running' => false,
            'success' => false,
            'console' => $current['console'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function status(int $serverId): ?array
    {
        $value = Cache::get($this->cacheKey($serverId));

        return is_array($value) ? $value : null;
    }

    public function clear(int $serverId): void
    {
        Cache::forget($this->cacheKey($serverId));
    }

    public function isStale(int $serverId, int $staleSeconds = 600): bool
    {
        $status = $this->status($serverId);

        if (! is_array($status) || ! ($status['running'] ?? false)) {
            return false;
        }

        $updatedAt = $status['updated_at'] ?? null;

        if (! is_string($updatedAt) || $updatedAt === '') {
            return true;
        }

        return \Illuminate\Support\Carbon::parse($updatedAt)->lte(now()->subSeconds($staleSeconds));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(int $serverId, array $payload): void
    {
        Cache::put($this->cacheKey($serverId), $payload, 3600);
    }
}
