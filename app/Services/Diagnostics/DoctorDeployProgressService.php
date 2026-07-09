<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Events\ProgressUpdated;
use App\Services\Deploy\DeployLogService;
use App\Support\Realtime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Suivi de progression des déploiements Doctor (cache + broadcast optionnel).
 */
final class DoctorDeployProgressService
{
    public const SCOPE = 'doctor';

    public const KEY = 'deploy';

    private const MAX_CONSOLE_LINES = 500;

    public function cacheKey(int $serverId): string
    {
        return 'doctor_deploy:'.$serverId;
    }

    public function start(int $serverId): void
    {
        $this->store($serverId, [
            'progress' => 5,
            'message' => 'Préparation du déploiement…',
            'running' => true,
            'steps' => [],
            'console' => ['['.now()->format('H:i:s').'] Déploiement démarré…'],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function appendLog(int $serverId, string $line): void
    {
        $current = $this->status($serverId) ?? [
            'progress' => 5,
            'message' => '',
            'running' => true,
            'steps' => [],
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
        app(DeployLogService::class)->log($serverId, 'doctor', $line, $level);
    }

    /**
     * @param  list<array{component: string, success: bool, output: string}>  $steps
     */
    public function update(int $serverId, int $progress, string $message, array $steps = []): void
    {
        $current = $this->status($serverId) ?? [];

        $this->store($serverId, [
            'progress' => $progress,
            'message' => $message,
            'running' => true,
            'steps' => $steps !== [] ? $steps : ($current['steps'] ?? []),
            'console' => $current['console'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  list<array{component: string, success: bool, output: string}>  $steps
     */
    public function finish(int $serverId, bool $success, string $message, array $steps = [], string $log = ''): void
    {
        $current = $this->status($serverId) ?? [];

        $this->store($serverId, [
            'progress' => 100,
            'message' => $message,
            'running' => false,
            'success' => $success,
            'steps' => $steps,
            'log' => $log !== '' ? $log : $message,
            'console' => $current['console'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ]);

        app(DeployLogService::class)->log(
            $serverId,
            'doctor',
            $message,
            $success ? 'info' : 'error',
        );
    }

    public function cancel(int $serverId, string $reason = 'Déploiement annulé.'): void
    {
        $current = $this->status($serverId) ?? [];
        $this->appendLog($serverId, $reason);
        $current = $this->status($serverId) ?? [];

        $this->store($serverId, [
            'progress' => (int) ($current['progress'] ?? 0),
            'message' => $reason,
            'running' => false,
            'success' => false,
            'steps' => $current['steps'] ?? [],
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

    public function isStale(int $serverId, int $staleSeconds = 180): bool
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

        if (! Realtime::enabled()) {
            return;
        }

        try {
            event(new ProgressUpdated($serverId, self::SCOPE, self::KEY, $payload));
        } catch (\Throwable $e) {
            Log::warning('Doctor deploy broadcast failed', [
                'server_id' => $serverId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
