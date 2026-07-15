<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class UserTimezone
{
    public static function resolve(?\App\Models\User $user = null): string
    {
        $user ??= auth()->user();

        $tz = is_string($user?->timezone) ? trim($user->timezone) : '';

        if ($tz !== '' && TimezoneCatalog::isValid($tz)) {
            return $tz;
        }

        return config('obiora.default_timezone', 'Europe/Paris');
    }

    public static function now(?\App\Models\User $user = null): Carbon
    {
        return Carbon::now(self::resolve($user));
    }

    public static function format(?CarbonInterface $at, string $format = 'd/m/Y H:i:s', ?\App\Models\User $user = null): string
    {
        if ($at === null) {
            return '—';
        }

        return $at->copy()->timezone(self::resolve($user))->format($format);
    }

    public static function label(?\App\Models\User $user = null): string
    {
        return TimezoneCatalog::label(self::resolve($user));
    }
}
