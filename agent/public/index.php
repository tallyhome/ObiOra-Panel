<?php

declare(strict_types=1);

/**
 * ObiOra Agent — API HTTP légère pour serveurs distants.
 * Lancé via: php agent/public/index.php (ou obiOra-agent start)
 */

$configPath = dirname(__DIR__).'/config/agent.json';
$config = file_exists($configPath)
    ? json_decode((string) file_get_contents($configPath), true)
    : [];

$token = $config['token'] ?? getenv('OBIORA_AGENT_TOKEN') ?: '';
$host = $config['host'] ?? '0.0.0.0';
$port = (int) ($config['port'] ?? 9100);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearer = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

header('Content-Type: application/json');

if ($bearer !== $token || $token === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

match (true) {
    $method === 'GET' && $uri === '/api/v1/ping' => respond(['status' => 'ok', 'agent' => 'obiOra', 'version' => '1.0.0']),
    $method === 'GET' && $uri === '/api/v1/metrics' => respond(['data' => collectMetrics()]),
    default => (function () {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    })(),
};

function respond(array $data): void
{
    echo json_encode($data);
}

/**
 * @return array<string, mixed>
 */
function collectMetrics(): array
{
    if (PHP_OS_FAMILY !== 'Linux') {
        return ['hostname' => gethostname(), 'os' => PHP_OS_FAMILY];
    }

    $mem = file_get_contents('/proc/meminfo') ?: '';
    preg_match('/MemTotal:\s+(\d+)/', $mem, $total);
    preg_match('/MemAvailable:\s+(\d+)/', $mem, $avail);
    $memTotal = ((int) ($total[1] ?? 0)) * 1024;
    $memAvail = ((int) ($avail[1] ?? 0)) * 1024;
    $memUsed = max(0, $memTotal - $memAvail);
    $load = sys_getloadavg() ?: [0, 0, 0];
    $diskTotal = disk_total_space('/') ?: 1;
    $diskFree = disk_free_space('/') ?: 0;

    return [
        'cpu' => [
            'load_1' => round($load[0], 2),
            'load_5' => round($load[1], 2),
            'load_15' => round($load[2], 2),
            'cores' => substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor') ?: 1,
        ],
        'memory' => [
            'total' => $memTotal,
            'used' => $memUsed,
            'free' => $memAvail,
            'percent' => $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0,
        ],
        'swap' => ['total' => 0, 'used' => 0, 'percent' => 0],
        'disk' => [
            'total' => $diskTotal,
            'used' => $diskTotal - $diskFree,
            'free' => $diskFree,
            'percent' => round((($diskTotal - $diskFree) / $diskTotal) * 100, 1),
        ],
        'uptime' => 'N/A',
        'hostname' => gethostname() ?: 'remote',
        'os' => php_uname('s').' '.php_uname('r'),
    ];
}
