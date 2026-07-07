<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Server;
use Illuminate\Support\Facades\Process;

/**
 * Résout l'hôte public à afficher pour accéder aux apps (Marketplace, etc.).
 * Évite 127.0.0.1 / localhost quand le panel est atteint via IP ou domaine.
 */
final class ServerAccessHost
{
    public function resolve(?Server $server = null): string
    {
        $appHost = $this->hostFromAppUrl();
        if ($appHost !== null) {
            return $appHost;
        }

        if ($server !== null) {
            if ($this->isUsableHost($server->hostname) && $this->looksLikeFqdn($server->hostname)) {
                return $server->hostname;
            }

            if ($this->isUsableHost($server->ip_address)) {
                return $server->ip_address;
            }
        }

        $detected = $this->detectLinuxIp();
        if ($detected !== null) {
            return $detected;
        }

        $requestHost = request()->getHost();
        if ($this->isUsableHost($requestHost)) {
            return $requestHost;
        }

        if ($server !== null && is_string($server->hostname) && $server->hostname !== '') {
            return $server->hostname;
        }

        if ($server !== null && is_string($server->ip_address) && $server->ip_address !== '') {
            return $server->ip_address;
        }

        return 'localhost';
    }

    private function hostFromAppUrl(): ?string
    {
        $url = (string) config('app.url', '');

        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! $this->isUsableHost($host)) {
            return null;
        }

        return $host;
    }

    private function detectLinuxIp(): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }

        $result = Process::timeout(5)->run('hostname -I 2>/dev/null');

        if (! $result->successful()) {
            return null;
        }

        foreach (preg_split('/\s+/', trim($result->output())) ?: [] as $ip) {
            if ($this->isUsableHost($ip)) {
                return $ip;
            }
        }

        return null;
    }

    private function isUsableHost(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $normalized = strtolower($host);

        if (in_array($normalized, ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true)) {
            return false;
        }

        return ! str_starts_with($normalized, '127.');
    }

    private function looksLikeFqdn(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return str_contains($host, '.');
    }
}
