<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fuseaux horaires proposés dans l'UI Doctor.
 */
final class TimezoneCatalog
{
    /**
     * @return array<string, string> IANA => libellé
     */
    public static function choices(): array
    {
        return [
            'Europe/Paris' => 'Europe/Paris (GMT+1 / heure d\'été CET)',
            'Europe/London' => 'Europe/Londres (GMT / BST)',
            'Europe/Berlin' => 'Europe/Berlin (CET)',
            'Europe/Brussels' => 'Europe/Bruxelles (CET)',
            'Europe/Zurich' => 'Europe/Zurich (CET)',
            'Europe/Madrid' => 'Europe/Madrid (CET)',
            'Europe/Rome' => 'Europe/Rome (CET)',
            'Europe/Amsterdam' => 'Europe/Amsterdam (CET)',
            'UTC' => 'UTC (GMT+0)',
            'America/New_York' => 'Amérique / New York (EST)',
            'America/Chicago' => 'Amérique / Chicago (CST)',
            'America/Los_Angeles' => 'Amérique / Los Angeles (PST)',
            'America/Montreal' => 'Amérique / Montréal (EST)',
            'Asia/Tokyo' => 'Asie / Tokyo (JST)',
            'Asia/Dubai' => 'Asie / Dubaï (GST)',
            'Asia/Singapore' => 'Asie / Singapour (SGT)',
            'Africa/Casablanca' => 'Afrique / Casablanca',
            'Indian/Reunion' => 'Océan Indien / La Réunion',
        ];
    }

    public static function isValid(string $timezone): bool
    {
        return array_key_exists($timezone, self::choices());
    }

    public static function label(string $timezone): string
    {
        return self::choices()[$timezone] ?? $timezone;
    }
}
