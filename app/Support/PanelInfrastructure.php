<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PDOException;
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
     * @return array{ready: bool, database: bool, redis: ?bool, redis_required: bool, hints: list<string>}
     */
    public static function diagnostics(bool $forceCheck = true): array
    {
        $database = PanelDatabase::isAvailable($forceCheck);
        $redisRequired = self::isRedisRequired();
        $redis = $redisRequired ? self::isRedisAvailable($forceCheck) : null;
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
            $hints[] = 'Redis injoignable — sudo systemctl restart redis ; le panel peut démarrer sans Redis (cache BDD).';
        }

        if ($database && $redisRequired && $redis === false) {
            $hints[] = 'Contournement : CACHE_STORE=database dans .env puis config:clear et restart php-fpm.';
        }

        return [
            'ready' => $database,
            'database' => $database,
            'database_error' => PanelDatabase::lastError(),
            'redis' => $redis,
            'redis_required' => $redisRequired,
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

    public static function fallbackCacheOffRedis(): void
    {
        if ((string) config('cache.default') !== 'redis') {
            return;
        }

        if (self::isRedisAvailable(true)) {
            return;
        }

        config(['cache.default' => 'database']);
    }

    public static function resetCache(): void
    {
        PanelDatabase::resetCache();
        self::$redisAvailable = null;
        self::$redisCheckedAt = 0;
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
