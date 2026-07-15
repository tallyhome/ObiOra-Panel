<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Fuseau d'affichage panel (bannière démo, etc.).
 * Toujours Europe/Paris par défaut — indépendant de APP_TIMEZONE (souvent UTC en BDD).
 */
final class PanelTimezone
{
    public static function resolve(): string
    {
        $candidate = config('obiora.default_timezone', 'Europe/Paris');

        if (is_string($candidate) && $candidate !== '' && TimezoneCatalog::isValid($candidate)) {
            return $candidate;
        }

        return 'Europe/Paris';
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::resolve());
    }

    public static function format(?CarbonInterface $at, string $format = 'd/m/Y H:i:s'): string
    {
        if ($at === null) {
            return '—';
        }

        // Timestamp Unix = instant absolu → évite les pièges du cast Eloquent / APP_TIMEZONE
        return Carbon::createFromTimestampUTC($at->getTimestamp())
            ->setTimezone(self::resolve())
            ->format($format);
    }

    public static function label(): string
    {
        return TimezoneCatalog::label(self::resolve());
    }
}
