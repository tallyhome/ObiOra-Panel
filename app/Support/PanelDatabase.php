<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

final class PanelDatabase
{
    private static ?bool $available = null;

    private static int $checkedAt = 0;

    private static ?string $lastError = null;

    private const SUCCESS_TTL_SECONDS = 15;

    private const FAILURE_RETRY_SECONDS = 2;

    public static function isAvailable(bool $forceCheck = false): bool
    {
        if (! $forceCheck && self::$available !== null && self::cacheValid()) {
            return self::$available;
        }

        self::$lastError = null;

        try {
            DB::connection()->getPdo();
            DB::connection()->select('select 1 as ok');

            self::$available = true;
        } catch (QueryException|PDOException $e) {
            self::$available = false;
            self::$lastError = self::sanitizeError($e->getMessage());
        } catch (Throwable $e) {
            self::$available = false;
            self::$lastError = self::sanitizeError($e->getMessage());
        }

        self::$checkedAt = time();

        return self::$available;
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function resetCache(): void
    {
        self::$available = null;
        self::$checkedAt = 0;
        self::$lastError = null;
    }

    private static function sanitizeError(string $message): string
    {
        $message = preg_replace('/(password=)\S+/i', '$1***', $message) ?? $message;

        return preg_replace('/using password:\s*\S+/i', 'using password: ***', $message) ?? $message;
    }

    private static function cacheValid(): bool
    {
        if (self::$checkedAt === 0) {
            return false;
        }

        $ttl = self::$available ? self::SUCCESS_TTL_SECONDS : self::FAILURE_RETRY_SECONDS;

        return (time() - self::$checkedAt) < $ttl;
    }
}
