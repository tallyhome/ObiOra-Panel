<?php

declare(strict_types=1);

/**
 * ObiOra Agent — API HTTP pour serveurs slaves.
 */

$configPath = dirname(__DIR__).'/config/agent.json';
$config = file_exists($configPath)
    ? json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR)
    : [];

require_once dirname(__DIR__).'/lib/websites.php';
require_once dirname(__DIR__).'/lib/mysql.php';
require_once dirname(__DIR__).'/lib/docker.php';

$token = $config['token'] ?? getenv('OBIORA_AGENT_TOKEN') ?: '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearer = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

header('Content-Type: application/json');

if ($bearer !== $token || $token === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $raw = (string) file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
}

match (true) {
    $method === 'GET' && $uri === '/api/v1/ping' => respond(pingInfo()),
    $method === 'GET' && $uri === '/api/v1/metrics' => respond(['data' => collectMetrics()]),
    $method === 'GET' && $uri === '/api/v1/services' => respond(['data' => listServices()]),
    $method === 'GET' && $uri === '/api/v1/services/logs' => respond(['output' => serviceLogs(
        (string) ($_GET['name'] ?? ''),
        (int) ($_GET['lines'] ?? 100)
    )]),
    $method === 'POST' && $uri === '/api/v1/services/action' => respond(serviceAction(
        (string) ($body['service'] ?? ''),
        (string) ($body['action'] ?? '')
    )),
    $method === 'POST' && $uri === '/api/v1/execute' => respond(executeCommand((string) ($body['command'] ?? ''))),
    $method === 'GET' && $uri === '/api/v1/websites' => respond(['data' => agentListWebsites((string) ($_GET['web_root'] ?? '/var/www'))]),
    $method === 'POST' && $uri === '/api/v1/websites' => respond(agentCreateWebsite($body)),
    $method === 'DELETE' && $uri === '/api/v1/websites' => respond(agentDeleteWebsite($body)),
    $method === 'POST' && $uri === '/api/v1/websites/ssl' => respond(agentIssueSsl($body)),
    $method === 'GET' && $uri === '/api/v1/databases' => respond(['data' => agentListDatabases()]),
    $method === 'POST' && $uri === '/api/v1/databases' => respond(agentCreateDatabase($body)),
    $method === 'DELETE' && $uri === '/api/v1/databases' => respond(agentDeleteDatabase($body)),
    $method === 'GET' && $uri === '/api/v1/docker/info' => respond(['data' => agentDockerInfo()]),
    $method === 'GET' && $uri === '/api/v1/docker/containers' => respond(['data' => agentListContainers()]),
    $method === 'GET' && $uri === '/api/v1/docker/images' => respond(['data' => agentListImages()]),
    $method === 'GET' && $uri === '/api/v1/docker/containers/logs' => respond(agentContainerLogs(
        (string) ($_GET['container'] ?? ''),
        (int) ($_GET['lines'] ?? 100)
    )),
    $method === 'POST' && $uri === '/api/v1/docker/containers/action' => respond(agentContainerAction($body)),
    $method === 'POST' && $uri === '/api/v1/docker/containers/run' => respond(agentRunContainer($body)),
    $method === 'DELETE' && $uri === '/api/v1/docker/images' => respond(agentRemoveImage($body)),
    default => abort404(),
};

function respond(array $data): void
{
    echo json_encode($data);
    exit;
}

function abort404(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

/**
 * @return array<string, mixed>
 */
function pingInfo(): array
{
    return [
        'status' => 'ok',
        'agent' => 'obiOra',
        'version' => '1.6.0',
        'role' => 'slave',
        'hostname' => gethostname() ?: 'unknown',
        'ip' => getServerIp(),
        'os' => PHP_OS_FAMILY === 'Linux' ? php_uname('s').' '.php_uname('r') : PHP_OS_FAMILY,
    ];
}

function getServerIp(): string
{
    $output = shell_exec("hostname -I 2>/dev/null") ?: '';

    return trim(explode(' ', trim($output))[0] ?? '127.0.0.1');
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

/**
 * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
 */
function listServices(): array
{
    if (PHP_OS_FAMILY !== 'Linux') {
        return [];
    }

    $output = shell_exec('systemctl list-units --type=service --all --no-pager --no-legend 2>/dev/null') ?: '';
    $services = [];

    foreach (explode("\n", trim($output)) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = preg_split('/\s+/', trim($line), 5);
        if (count($parts) < 4) {
            continue;
        }
        $services[] = [
            'name' => $parts[0],
            'load' => $parts[1],
            'active' => $parts[2],
            'sub' => $parts[3],
            'description' => $parts[4] ?? '',
        ];
    }

    return $services;
}

/**
 * @return array<string, mixed>
 */
function serviceAction(string $service, string $action): array
{
    $allowed = ['start', 'stop', 'restart', 'reload', 'enable', 'disable'];

    if (! preg_match('/^[a-zA-Z0-9@._\-]+$/', $service)) {
        http_response_code(422);

        return ['error' => 'Nom de service invalide'];
    }

    if (! in_array($action, $allowed, true)) {
        http_response_code(422);

        return ['error' => 'Action non autorisée'];
    }

    $output = [];
    $exit = 0;
    exec('systemctl '.escapeshellarg($action).' '.escapeshellarg($service).' 2>&1', $output, $exit);

    return [
        'success' => $exit === 0,
        'output' => trim(implode("\n", $output)),
        'exit_code' => $exit,
    ];
}

function serviceLogs(string $name, int $lines): string
{
    if ($name === '') {
        return '';
    }

    $lines = max(10, min($lines, 500));
    $name = escapeshellarg($name);

    return trim(shell_exec("journalctl -u {$name} -n {$lines} --no-pager 2>&1") ?: '');
}

/**
 * @return array<string, mixed>
 */
function executeCommand(string $command): array
{
    if ($command === '') {
        return ['error' => 'Commande vide', 'exit_code' => 1];
    }

    $output = shell_exec($command.' 2>&1') ?? '';

    return [
        'output' => $output,
        'exit_code' => 0,
    ];
}
