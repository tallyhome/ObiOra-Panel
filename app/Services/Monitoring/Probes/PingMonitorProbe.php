<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;
use Symfony\Component\Process\Process;

final class PingMonitorProbe implements MonitorProbe
{
    public function check(Monitor $monitor): MonitorCheckResult
    {
        $host = $this->sanitizeHost($monitor->target);

        if ($host === '') {
            return new MonitorCheckResult('down', null, [], 'Hôte invalide');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->tcpPingFallback($host);
        }

        $start = microtime(true);
        $process = new Process(['ping', '-c', '1', '-W', '2', $host]);
        $process->setTimeout(10);
        $process->run();
        $responseMs = (int) round((microtime(true) - $start) * 1000);

        if ($process->isSuccessful()) {
            return new MonitorCheckResult('up', $responseMs, ['method' => 'icmp']);
        }

        return $this->tcpPingFallback($host, $responseMs, $process->getErrorOutput() ?: 'ICMP échoué');
    }

    private function tcpPingFallback(string $host, ?int $icmpMs = null, ?string $icmpError = null): MonitorCheckResult
    {
        $ports = [443, 80, 22];
        $start = microtime(true);

        foreach ($ports as $port) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 3);

            if ($socket !== false) {
                fclose($socket);
                $responseMs = (int) round((microtime(true) - $start) * 1000);

                return new MonitorCheckResult(
                    status: 'up',
                    responseMs: $responseMs,
                    metrics: [
                        'method' => 'tcp',
                        'tcp_port' => $port,
                        'icmp_ms' => $icmpMs,
                    ],
                );
            }
        }

        $responseMs = (int) round((microtime(true) - $start) * 1000);

        return new MonitorCheckResult(
            status: 'down',
            responseMs: $responseMs,
            metrics: ['method' => 'tcp'],
            error: $icmpError ?? 'Hôte injoignable',
        );
    }

    private function sanitizeHost(string $target): string
    {
        $target = trim($target);
        $target = preg_replace('#^https?://#i', '', $target) ?? $target;

        return explode('/', $target)[0] ?? $target;
    }
}
