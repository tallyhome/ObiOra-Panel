<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Santé des dépendances panel au boot (MariaDB + Redis si requis).
 */
final class PanelInfrastructure
{
    private static ?bool $redisAvailable = null;

    private static int $redisCheckedAt = 0;

    private const REDIS_SUCCESS_TTL_SECONDS = 15;

    private const REDIS_FAILURE_RETRY_SECONDS = 2;

    /**
     * Le panel web ne doit bloquer que si MariaDB est indisponible.
     * Redis est requis pour le cache perf mais pas pour servir l'UI.
     */
    public static function isReady(bool $forceCheck = false): bool
    {
        return PanelDatabase::isAvailable($forceCheck);
    }

    /**
     * @return array{
     *     ready: bool,
     *     database: bool,
     *     database_error: ?string,
     *     redis: ?bool,
     *     redis_required: bool,
     *     disk_ok: bool,
     *     disk_free_bytes: int,
     *     disk_used_percent: ?float,
     *     hints: list<string>
     * }
     */
    public static function diagnostics(bool $forceCheck = true): array
    {
        $database = PanelDatabase::isAvailable($forceCheck);
        $redisRequired = self::isRedisRequired();
        $redis = $redisRequired ? self::isRedisAvailable($forceCheck) : null;
        $disk = self::diskStatus();
        $hints = [];

        if (! $database) {
            $dbError = PanelDatabase::lastError();
            $hints[] = 'MariaDB injoignable — sudo systemctl restart mariadb puis php artisan config:clear';
            if ($dbError !== null && $dbError !== '') {
                $hints[] = 'Erreur : '.$dbError;
            }
            $hints[] = 'SSH : sudo bash /opt/obiora-panel/agent/scripts/panel-recover-ssh.sh';
        }

        if ($redisRequired && $redis === false) {
            $hints[] = 'Redis injoignable — bascule auto cache/session ; ou CACHE_STORE=database dans .env';
        }

        if (! $disk['ok']) {
            $hints[] = 'Disque critique ('.$disk['used_percent'].'% utilisé, '
                .self::formatBytes($disk['free_bytes']).' libre) — purge CrashHunter : '
                .'sudo bash /opt/obiora-panel/agent/scripts/crashhunter-disk-purge.sh keep 3';
            $hints[] = 'Ou recover complet : sudo bash /opt/obiora-panel/agent/scripts/panel-recover-ssh.sh';
        }

        return [
            'ready' => $database && $disk['ok'],
            'database' => $database,
            'database_error' => PanelDatabase::lastError(),
            'redis' => $redis,
            'redis_required' => $redisRequired,
            'disk_ok' => $disk['ok'],
            'disk_free_bytes' => $disk['free_bytes'],
            'disk_used_percent' => $disk['used_percent'],
            'hints' => $hints,
        ];
    }

    public static function isRedisRequired(): bool
    {
        $stores = [
            (string) config('cache.default'),
            (string) config('session.driver'),
            (string) config('queue.default'),
        ];

        $permissionStore = config('permission.cache.store', 'default');
        if ($permissionStore === 'default') {
            $stores[] = (string) config('cache.default');
        } else {
            $stores[] = (string) $permissionStore;
        }

        foreach ($stores as $driver) {
            if ($driver === 'redis') {
                return true;
            }
        }

        return false;
    }

    public static function isRedisAvailable(bool $forceCheck = false): bool
    {
        if (! self::isRedisRequired()) {
            return true;
        }

        if (! $forceCheck && self::$redisAvailable !== null && self::redisCacheValid()) {
            return self::$redisAvailable;
        }

        if (! self::canReachRedisPort()) {
            self::$redisAvailable = false;
            self::$redisCheckedAt = time();

            return false;
        }

        try {
            $ping = Redis::connection()->ping();
            self::$redisAvailable = $ping === true || $ping === 'PONG' || $ping === '+PONG';
        } catch (Throwable) {
            self::$redisAvailable = false;
        }

        self::$redisCheckedAt = time();

        return self::$redisAvailable;
    }

    /**
     * Bascule cache (+ session) hors Redis et invalide les singletons Laravel
     * pour que RateLimiter / Login n'utilisent plus une connexion morte.
     */
    public static function fallbackCacheOffRedis(): void
    {
        $redisDown = ! self::isRedisAvailable(true);

        if ((string) config('cache.default') === 'redis' && $redisDown) {
            config(['cache.default' => 'database']);
            self::forgetCacheInstances();
        }

        if ((string) config('session.driver') === 'redis' && $redisDown) {
            config(['session.driver' => 'file']);
        }
    }

    /**
     * @return array{ok: bool, free_bytes: int, used_percent: ?float, path: string}
     */
    public static function diskStatus(?string $path = null): array
    {
        $path = $path ?? (function_exists('base_path') ? base_path() : '/opt/obiora-panel');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return ['ok' => true, 'free_bytes' => 0, 'used_percent' => null, 'path' => $path];
        }

        $freeBytes = (int) $free;
        $usedPercent = round((1 - ($freeBytes / (float) $total)) * 100, 1);
        // Critique : < 400 Mo libres OU > 96 % utilisés
        $ok = $freeBytes >= 400 * 1024 * 1024 && $usedPercent < 96.0;

        return [
            'ok' => $ok,
            'free_bytes' => $freeBytes,
            'used_percent' => $usedPercent,
            'path' => $path,
        ];
    }

    /**
     * Tente une purge CrashHunter si le disque est critique (best-effort, non bloquant longtemps).
     */
    public static function reclaimDiskIfCritical(): bool
    {
        $disk = self::diskStatus();
        if ($disk['ok']) {
            return false;
        }

        $script = base_path('agent/scripts/crashhunter-disk-purge.sh');
        if (! is_file($script) || PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $cmd = 'sudo -n '.escapeshellarg($script).' keep 2 2>/dev/null';
        @exec($cmd, $output, $code);

        return $code === 0;
    }

    public static function resetCache(): void
    {
        PanelDatabase::resetCache();
        self::$redisAvailable = null;
        self::$redisCheckedAt = 0;
    }

    public static function isDiskSpaceException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'no space left')
            || str_contains($message, 'enospc')
            || str_contains($message, 'disk quota exceeded')
            || (str_contains($message, 'failed to open stream') && str_contains($message, 'no space'));
    }

    private static function forgetCacheInstances(): void
    {
        try {
            if (function_exists('app') && app()->bound('cache')) {
                app()->forgetInstance('cache');
            }
            if (function_exists('app') && app()->bound('cache.store')) {
                app()->forgetInstance('cache.store');
            }
        } catch (Throwable) {
            // ignore
        }
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1).' Go';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 0).' Mo';
        }

        return number_format(max(0, $bytes) / 1024, 0).' Ko';
    }

    private static function canReachRedisPort(): bool
    {
        $host = (string) config('database.redis.default.host', '127.0.0.1');
        $port = (int) config('database.redis.default.port', 6379);

        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private static function redisCacheValid(): bool
    {
        if (self::$redisCheckedAt === 0) {
            return false;
        }

        $ttl = self::$redisAvailable ? self::REDIS_SUCCESS_TTL_SECONDS : self::REDIS_FAILURE_RETRY_SECONDS;

        return (time() - self::$redisCheckedAt) < $ttl;
    }
}
