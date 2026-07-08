<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Server;
use App\Models\ServerPingSample;
use Illuminate\Support\Facades\Log;

final class ServerPingService
{
    public function probe(Server $server): ServerPingSample
    {
        $host = $this->resolveHost($server);
        $result = $this->icmpPing($host);

        if (! $result['success']) {
            $port = $this->resolvePort($server);
            $result = $this->tcpPing($host, $port);
        }

        $sample = ServerPingSample::query()->create([
            'server_id' => $server->id,
            'latency_ms' => $result['latency_ms'],
            'success' => $result['success'],
            'method' => $result['method'],
            'sampled_at' => now(),
        ]);

        $metadata = array_merge($server->metadata ?? [], [
            'ping' => [
                'latency_ms' => $result['latency_ms'],
                'success' => $result['success'],
                'method' => $result['method'],
                'at' => now()->toIso8601String(),
            ],
        ]);

        $server->forceFill([
            'metadata' => $metadata,
            'last_seen_at' => $result['success'] ? now() : $server->last_seen_at,
        ])->save();

        return $sample;
    }

    /**
     * @return array{success: bool, latency_ms: int|null, method: string}
     */
    private function icmpPing(string $host): array
    {
        if ($host === '') {
            return ['success' => false, 'latency_ms' => null, 'method' => 'icmp'];
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $command = $isWindows
            ? ['ping', '-n', '1', '-w', '1000', $host]
            : ['ping', '-c', '1', '-W', '1', $host];

        $start = hrtime(true);
        $output = [];
        $exitCode = 1;

        try {
            $process = proc_open(
                $command,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]) ?: '';
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
            }
        } catch (\Throwable $exception) {
            Log::debug('ICMP ping failed', ['host' => $host, 'error' => $exception->getMessage()]);

            return ['success' => false, 'latency_ms' => null, 'method' => 'icmp'];
        }

        $latency = $this->parseLatencyFromPingOutput($output, $isWindows);
        if ($latency === null && $exitCode === 0) {
            $latency = (int) round((hrtime(true) - $start) / 1_000_000);
        }

        return [
            'success' => $exitCode === 0,
            'latency_ms' => $latency,
            'method' => 'icmp',
        ];
    }

    /**
     * @return array{success: bool, latency_ms: int|null, method: string}
     */
    private function tcpPing(string $host, int $port): array
    {
        if ($host === '') {
            return ['success' => false, 'latency_ms' => null, 'method' => 'tcp'];
        }

        $start = hrtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
        $latency = (int) round((hrtime(true) - $start) / 1_000_000);

        if ($socket === false) {
            return ['success' => false, 'latency_ms' => null, 'method' => 'tcp'];
        }

        fclose($socket);

        return ['success' => true, 'latency_ms' => $latency, 'method' => 'tcp'];
    }

    private function parseLatencyFromPingOutput(string $output, bool $isWindows): ?int
    {
        if ($isWindows) {
            if (preg_match('/time[=<](\d+)ms/i', $output, $matches)) {
                return (int) $matches[1];
            }

            return null;
        }

        if (preg_match('/time=(\d+(?:\.\d+)?)\s*ms/i', $output, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }

    private function resolveHost(Server $server): string
    {
        $host = trim((string) ($server->ip_address ?? ''));
        if ($host === '' && is_string($server->hostname)) {
            $host = trim($server->hostname);
        }

        return $host;
    }

    private function resolvePort(Server $server): int
    {
        if (! $server->relationLoaded('primaryNode')) {
            $server->load('primaryNode');
        }

        return (int) ($server->primaryNode?->port ?? $server->metadata['agent_port'] ?? 9100);
    }
}
