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

    public static function isReady(bool $forceCheck = false): bool
    {
        return PanelDatabase::isAvailable($forceCheck) && self::isRedisAvailable($forceCheck);
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

        try {
            $ping = Redis::connection()->ping();
            self::$redisAvailable = $ping === true || $ping === 'PONG' || $ping === '+PONG';
        } catch (Throwable) {
            self::$redisAvailable = false;
        }

        self::$redisCheckedAt = time();

        return self::$redisAvailable;
    }

    public static function resetCache(): void
    {
        PanelDatabase::resetCache();
        self::$redisAvailable = null;
        self::$redisCheckedAt = 0;
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
