<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Events\ProgressUpdated;
use App\Support\Realtime;
use Illuminate\Support\Facades\Cache;

/**
 * Suivi de progression des déploiements Doctor (cache + broadcast optionnel).
 */
final class DoctorDeployProgressService
{
    public const SCOPE = 'doctor';

    public const KEY = 'deploy';

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
            'updated_at' => now()->toIso8601String(),
        ]);
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
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  list<array{component: string, success: bool, output: string}>  $steps
     */
    public function finish(int $serverId, bool $success, string $message, array $steps = [], string $log = ''): void
    {
        $this->store($serverId, [
            'progress' => 100,
            'message' => $message,
            'running' => false,
            'success' => $success,
            'steps' => $steps,
            'log' => $log !== '' ? $log : $message,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(int $serverId, array $payload): void
    {
        Cache::put($this->cacheKey($serverId), $payload, 3600);

        if (Realtime::enabled()) {
            event(new ProgressUpdated($serverId, self::SCOPE, self::KEY, $payload));
        }
    }
}
