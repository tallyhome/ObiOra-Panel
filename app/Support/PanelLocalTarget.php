<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Server;

/**
 * Détecte si une cible SSH correspond au serveur qui héberge le panel.
 */
final class PanelLocalTarget
{
    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host));

        if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return true;
        }

        return in_array($host, self::localAddresses(), true);
    }

    public static function isPanelServer(Server $server, string $sshHost): bool
    {
        $sshHost = trim($sshHost);

        if ($sshHost === '') {
            return false;
        }

        if (self::isLocalHost($sshHost)) {
            return true;
        }

        if ($server->is_master || $server->type->value === 'local') {
            return $sshHost === trim((string) $server->ip_address);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function localAddresses(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return ['127.0.0.1'];
        }

        $ips = [];
        $output = shell_exec('hostname -I 2>/dev/null') ?? '';

        foreach (preg_split('/\s+/', trim($output)) ?: [] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        $appUrl = (string) config('app.url', '');
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (is_string($host) && filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        }

        return array_values(array_unique($ips));
    }
}
