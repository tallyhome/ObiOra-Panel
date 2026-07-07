<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Support\NetworkMetrics;
use Illuminate\Support\Facades\Http;

final class MetricsCollector
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly NetworkMetrics $networkMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return $this->emptyMetrics();
        }

        if ($server->is_master || $server->type->value === 'local') {
            return $this->collectLocal();
        }

        return $this->collectRemote($server);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectLocal(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return $this->devMetrics();
        }

        $meminfo = $this->parseMeminfo();
        $load = sys_getloadavg() ?: [0, 0, 0];
        $disk = disk_total_space('/') ?: 1;
        $diskFree = disk_free_space('/') ?: 0;

        return [
            'cpu' => [
                'load_1' => round($load[0], 2),
                'load_5' => round($load[1], 2),
                'load_15' => round($load[2], 2),
                'cores' => (int) ($this->countCpuCores() ?: 1),
            ],
            'memory' => [
                'total' => $meminfo['total'] ?? 0,
                'used' => $meminfo['used'] ?? 0,
                'free' => $meminfo['free'] ?? 0,
                'percent' => $meminfo['percent'] ?? 0,
            ],
            'swap' => [
                'total' => $meminfo['swap_total'] ?? 0,
                'used' => $meminfo['swap_used'] ?? 0,
                'percent' => $meminfo['swap_percent'] ?? 0,
            ],
            'disk' => [
                'total' => $disk,
                'used' => $disk - $diskFree,
                'free' => $diskFree,
                'percent' => $disk > 0 ? round((($disk - $diskFree) / $disk) * 100, 1) : 0,
            ],
            'network' => $this->networkMetrics->snapshot($this->serverManager->getCurrentServer()),
            'uptime' => $this->getUptime(),
            'hostname' => gethostname() ?: 'localhost',
            'os' => php_uname('s').' '.php_uname('r'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectRemote(Server $server): array
    {
        $node = $server->primaryNode;

        if ($node === null) {
            return $this->emptyMetrics();
        }

        try {
            $token = $server->agent_token;
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(10)
                ->withToken($token)
                ->get("http://{$host}:{$port}/api/v1/metrics");

            if ($response->successful()) {
                return $response->json('data', $this->emptyMetrics());
            }
        } catch (\Throwable) {
            //
        }

        return $this->emptyMetrics();
    }

    /**
     * @return array<string, int|float>
     */
    private function parseMeminfo(): array
    {
        $data = ['total' => 0, 'free' => 0, 'swap_total' => 0, 'swap_free' => 0];

        if (! is_readable('/proc/meminfo')) {
            return $data;
        }

        $lines = file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $data[strtolower($m[1])] = (int) $m[2] * 1024;
            }
        }

        $total = $data['memtotal'] ?? 0;
        $available = $data['memavailable'] ?? ($data['memfree'] ?? 0);
        $used = max(0, $total - $available);
        $swapTotal = $data['swaptotal'] ?? 0;
        $swapFree = $data['swapfree'] ?? 0;
        $swapUsed = max(0, $swapTotal - $swapFree);

        return [
            'total' => $total,
            'used' => $used,
            'free' => $available,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            'swap_total' => $swapTotal,
            'swap_used' => $swapUsed,
            'swap_percent' => $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0,
        ];
    }

    private function countCpuCores(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            return substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor');
        }

        return 1;
    }

    private function getUptime(): string
    {
        if (! is_readable('/proc/uptime')) {
            return 'N/A';
        }

        $seconds = (int) explode(' ', (string) file_get_contents('/proc/uptime'))[0];
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins = intdiv($seconds % 3600, 60);

        return "{$days}j {$hours}h {$mins}m";
    }

    /**
     * @return array<string, mixed>
     */
    private function devMetrics(): array
    {
        return [
            'cpu' => ['load_1' => 0.42, 'load_5' => 0.38, 'load_15' => 0.35, 'cores' => 4],
            'memory' => ['total' => 8_589_934_592, 'used' => 4_294_967_296, 'free' => 4_294_967_296, 'percent' => 50.0],
            'swap' => ['total' => 2_147_483_648, 'used' => 0, 'percent' => 0],
            'disk' => ['total' => 256_060_514_304, 'used' => 128_030_257_152, 'free' => 128_030_257_152, 'percent' => 50.0],
            'network' => $this->networkMetrics->snapshot(),
            'uptime' => '0j 2h 15m',
            'hostname' => gethostname() ?: 'dev',
            'os' => PHP_OS_FAMILY.' (dev)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(): array
    {
        return [
            'cpu' => ['load_1' => 0, 'load_5' => 0, 'load_15' => 0, 'cores' => 0],
            'memory' => ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0],
            'swap' => ['total' => 0, 'used' => 0, 'percent' => 0],
            'disk' => ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0],
            'network' => $this->networkMetrics->emptySnapshot(),
            'uptime' => 'N/A',
            'hostname' => 'offline',
            'os' => 'N/A',
        ];
    }
}
