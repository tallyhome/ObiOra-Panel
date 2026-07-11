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

    public static function isAvailable(bool $forceCheck = false): bool
    {
        if (! $forceCheck && self::$available !== null) {
            return self::$available;
        }

        try {
            DB::connection()->getPdo();
            DB::connection()->select('select 1 as ok');

            self::$available = true;
        } catch (QueryException|PDOException) {
            self::$available = false;
        } catch (Throwable) {
            self::$available = false;
        }

        return self::$available;
    }

    public static function resetCache(): void
    {
        self::$available = null;
    }
}
