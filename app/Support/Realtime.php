<?php

declare(strict_types=1);

namespace App\Support;

final class Realtime
{
    public static function enabled(): bool
    {
        if (! (bool) config('obiora.realtime.enabled', false)) {
            return false;
        }

        $connection = (string) config('broadcasting.default', 'null');

        return in_array($connection, ['reverb', 'pusher'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        return [
            'enabled' => self::enabled(),
            'broadcaster' => config('broadcasting.default'),
            'key' => env('REVERB_APP_KEY'),
            'host' => env('REVERB_HOST', parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'port' => (int) env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
        ];
    }
}
