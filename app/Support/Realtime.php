<?php

declare(strict_types=1);

namespace App\Support;

final class Realtime
{
    private static ?bool $reachable = null;

    private static int $reachableCheckedAt = 0;

    public static function enabled(): bool
    {
        if (! (bool) config('obiora.realtime.enabled', false)) {
            return false;
        }

        $connection = (string) config('broadcasting.default', 'null');

        if (! in_array($connection, ['reverb', 'pusher'], true)) {
            return false;
        }

        // Ne pas diffuser si le serveur Reverb est down (évite BroadcastException / 500).
        return self::isReachable();
    }

    public static function isReachable(bool $forceCheck = false): bool
    {
        if (! $forceCheck && self::$reachable !== null && (time() - self::$reachableCheckedAt) < 5) {
            return self::$reachable;
        }

        $host = (string) env('REVERB_SERVER_HOST', env('REVERB_HOST', '127.0.0.1'));
        $port = (int) env('REVERB_SERVER_PORT', env('REVERB_PORT', 8080));

        if ($host === 'localhost' || $host === '') {
            $host = '127.0.0.1';
        }

        // Si REVERB_HOST pointe vers l'IP publique, tester d'abord le loopback (service local).
        $candidates = array_unique([$host, '127.0.0.1']);

        $ok = false;
        foreach ($candidates as $candidate) {
            $socket = @fsockopen($candidate, $port, $errno, $errstr, 0.4);
            if ($socket !== false) {
                fclose($socket);
                $ok = true;
                break;
            }
        }

        self::$reachable = $ok;
        self::$reachableCheckedAt = time();

        return $ok;
    }

    public static function resetReachableCache(): void
    {
        self::$reachable = null;
        self::$reachableCheckedAt = 0;
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
