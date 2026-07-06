<?php

declare(strict_types=1);

/**
 * Fonctions MySQL/MariaDB pour l'agent ObiOra.
 */

function agentRunMysqlScript(string $script, array $args = []): string
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
 * @return list<string>
 */
function agentListDatabases(): array
{
    $script = dirname(__DIR__).'/scripts/mysql-list.sh';
    $output = agentRunMysqlScript($script);

    if (! str_starts_with($output, 'OK:')) {
        return [];
    }

    $payload = substr(trim($output), 3);

    if ($payload === '') {
        return [];
    }

    return array_values(array_filter(explode(',', $payload)));
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentCreateDatabase(array $body): array
{
    $name = (string) ($body['name'] ?? '');
    $username = (string) ($body['username'] ?? '');
    $password = isset($body['password']) ? (string) $body['password'] : '';

    if ($name === '') {
        http_response_code(422);

        return ['success' => false, 'output' => 'Nom requis'];
    }

    $script = dirname(__DIR__).'/scripts/mysql-create.sh';
    $args = [$name];
    if ($username !== '') {
        $args[] = $username;
    }
    if ($password !== '') {
        $args[] = $password;
    }

    $output = agentRunMysqlScript($script, $args);

    if (! preg_match('/OK:([^:]+):([^:]+):(.+)/', $output, $m)) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return [
        'success' => true,
        'username' => trim($m[2]),
        'password' => trim($m[3]),
        'output' => trim($output),
    ];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentDeleteDatabase(array $body): array
{
    $name = (string) ($body['name'] ?? '');
    $username = (string) ($body['username'] ?? '');

    if ($name === '') {
        http_response_code(422);

        return ['success' => false, 'output' => 'Nom requis'];
    }

    $script = dirname(__DIR__).'/scripts/mysql-delete.sh';
    $args = [$name];
    if ($username !== '') {
        $args[] = $username;
    }

    $output = agentRunMysqlScript($script, $args);

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return ['success' => true, 'output' => trim($output)];
}
