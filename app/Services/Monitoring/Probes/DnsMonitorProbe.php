<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Probes;

use App\Models\Monitor;

final class DnsMonitorProbe implements MonitorProbe
{
    public function check(Monitor $monitor): MonitorCheckResult
    {
        $host = $this->sanitizeHost($monitor->target);
        $recordType = strtoupper((string) ($monitor->keyword ?: 'A'));

        if ($host === '') {
            return new MonitorCheckResult('down', null, [], 'Nom d\'hôte invalide');
        }

        if (! in_array($recordType, ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS'], true)) {
            return new MonitorCheckResult('down', null, [], "Type DNS non supporté : {$recordType}");
        }

        $start = microtime(true);

        try {
            $records = @dns_get_record($host, $this->recordFlag($recordType));
            $responseMs = (int) round((microtime(true) - $start) * 1000);

            if ($records === false || $records === []) {
                return new MonitorCheckResult(
                    status: 'down',
                    responseMs: $responseMs,
                    metrics: ['dns_ms' => $responseMs, 'record_type' => $recordType],
                    error: 'Aucun enregistrement',
                );
            }

            return new MonitorCheckResult(
                status: 'up',
                responseMs: $responseMs,
                metrics: [
                    'dns_ms' => $responseMs,
                    'record_type' => $recordType,
                    'records_count' => count($records),
                ],
            );
        } catch (\Throwable $exception) {
            $responseMs = (int) round((microtime(true) - $start) * 1000);

            return new MonitorCheckResult('down', $responseMs, [], $exception->getMessage());
        }
    }

    private function sanitizeHost(string $target): string
    {
        $target = trim($target);
        $target = preg_replace('#^https?://#i', '', $target) ?? $target;

        return explode('/', $target)[0] ?? $target;
    }

    private function recordFlag(string $type): int
    {
        return match ($type) {
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            'NS' => DNS_NS,
            default => DNS_A,
        };
    }
}
