<?php

declare(strict_types=1);

/**
 * Fonctions Docker pour l'agent ObiOra.
 */

function agentRunDockerScript(string $script, array $args = []): string
{
    if (! is_file($script)) {
        http_response_code(500);

        return '';
    }

    $cmd = 'bash '.escapeshellarg($script);
    foreach ($args as $arg) {
        $cmd .= ' '.escapeshellarg((string) $arg);
    }

    if (PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        $cmd = 'sudo -n '.$cmd;
    }

    return shell_exec($cmd.' 2>&1') ?? '';
}

/**
 * @return array<string, mixed>
 */
function agentDockerInfo(): array
{
    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-info.sh');

    if (! str_starts_with($output, 'OK:')) {
        return [
            'installed' => false,
            'version' => null,
            'running' => 0,
            'total' => 0,
            'images' => 0,
            'error' => trim($output),
        ];
    }

    $parts = explode(':', trim($output), 5);

    return [
        'installed' => true,
        'version' => $parts[1] ?? null,
        'running' => (int) ($parts[2] ?? 0),
        'total' => (int) ($parts[3] ?? 0),
        'images' => (int) ($parts[4] ?? 0),
    ];
}

/**
 * @return list<array<string, string>>
 */
function agentListContainers(): array
{
    return agentParseRows(agentRunDockerScript(dirname(__DIR__).'/scripts/docker-containers.sh'));
}

/**
 * @return list<array<string, string>>
 */
function agentListImages(): array
{
    return agentParseRows(agentRunDockerScript(dirname(__DIR__).'/scripts/docker-images.sh'), ['id', 'repository', 'tag', 'size']);
}

/**
 * @param  list<string>  $fields
 * @return list<array<string, string>>
 */
function agentParseRows(string $output, array $fields = ['id', 'name', 'image', 'status', 'ports']): array
{
    if (! str_contains($output, 'OK')) {
        return [];
    }

    $rows = [];

    foreach (explode("\n", $output) as $line) {
        $line = trim($line);
        if (! str_starts_with($line, 'ROW:')) {
            continue;
        }
        $parts = explode(':', substr($line, 4), count($fields));
        $row = [];
        foreach ($fields as $i => $field) {
            $row[$field] = $parts[$i] ?? '';
        }
        $rows[] = $row;
    }

    return $rows;
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentContainerAction(array $body): array
{
    $container = (string) ($body['container'] ?? '');
    $action = (string) ($body['action'] ?? '');

    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-action.sh', [$container, $action]);

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return ['success' => true, 'output' => trim($output)];
}

/**
 * @return array<string, mixed>
 */
function agentContainerLogs(string $container, int $lines): array
{
    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-logs.sh', [$container, (string) $lines]);
    $output = preg_replace('/\nOK$/', '', $output) ?? $output;

    return ['output' => trim($output)];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentRunContainer(array $body): array
{
    $image = (string) ($body['image'] ?? '');
    $name = (string) ($body['name'] ?? '');
    $ports = (string) ($body['ports'] ?? '');

    $args = [$image];
    if ($name !== '') {
        $args[] = $name;
    }
    if ($ports !== '') {
        $args[] = $ports;
    }

    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-run.sh', $args);

    if (! preg_match('/OK:(.+)/', $output, $m)) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return [
        'success' => true,
        'container_id' => trim($m[1]),
        'output' => trim($output),
    ];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentRemoveImage(array $body): array
{
    $image = (string) ($body['image'] ?? '');
    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-rmi.sh', [$image]);

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return ['success' => true, 'output' => trim($output)];
}

/**
 * @return array<string, mixed>
 */
function agentDockerInstall(): array
{
    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-install.sh');

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'message' => trim($output) ?: 'Échec installation Docker'];
    }

    if (preg_match('/^OK:(.+)$/m', $output, $matches)) {
        $message = trim($matches[1]);

        return ['success' => true, 'message' => $message !== '' ? $message : 'Docker installé'];
    }

    return ['success' => true, 'message' => 'Docker installé'];
}

/**
 * @return array<string, mixed>
 */
function agentDockerUninstall(): array
{
    $output = agentRunDockerScript(dirname(__DIR__).'/scripts/docker-uninstall.sh');

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'message' => trim($output) ?: 'Échec désinstallation Docker'];
    }

    if (preg_match('/^OK:(.+)$/m', $output, $matches)) {
        $message = trim($matches[1]);

        return ['success' => true, 'message' => $message !== '' ? $message : 'Docker désinstallé'];
    }

    return ['success' => true, 'message' => 'Docker désinstallé'];
}
