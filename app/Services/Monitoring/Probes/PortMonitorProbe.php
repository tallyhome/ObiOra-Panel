<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;

final class PortMonitorProbe implements MonitorProbe
{
    public function check(Monitor $monitor): MonitorCheckResult
    {
        $host = $this->sanitizeHost($monitor->target);
        $port = (int) ($monitor->port ?? 0);

        if ($host === '' || $port < 1 || $port > 65535) {
            return new MonitorCheckResult('down', null, [], 'Hôte ou port invalide');
        }

        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        $responseMs = (int) round((microtime(true) - $start) * 1000);

        if ($socket === false) {
            return new MonitorCheckResult(
                status: 'down',
                responseMs: $responseMs,
                metrics: ['tcp_connect_ms' => $responseMs, 'port' => $port],
                error: $errstr !== '' ? $errstr : "Connexion refusée ({$errno})",
            );
        }

        fclose($socket);

        return new MonitorCheckResult(
            status: 'up',
            responseMs: $responseMs,
            metrics: ['tcp_connect_ms' => $responseMs, 'port' => $port],
        );
    }

    private function sanitizeHost(string $target): string
    {
        $target = trim($target);
        $target = preg_replace('#^https?://#i', '', $target) ?? $target;

        return explode('/', $target)[0] ?? $target;
    }
}
