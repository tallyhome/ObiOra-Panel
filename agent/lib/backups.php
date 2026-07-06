<?php

declare(strict_types=1);

/**
 * Fonctions sauvegardes pour l'agent ObiOra.
 */

function agentRunBackupScript(string $script, array $args = []): string
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
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentCreateBackup(array $body): array
{
    $type = (string) ($body['type'] ?? '');
    $label = (string) ($body['label'] ?? '');
    $target = isset($body['target']) ? (string) $body['target'] : '';

    if ($type === '' || $label === '') {
        http_response_code(422);

        return ['success' => false, 'output' => 'Type et label requis'];
    }

    $args = [$type, $label];
    if ($target !== '') {
        $args[] = $target;
    }

    $output = agentRunBackupScript(dirname(__DIR__).'/scripts/backup-create.sh', $args);

    if (! preg_match('/OK:([^:]+):([^:]+):(\d+):/', $output, $m)) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return [
        'success' => true,
        'storage_path' => $m[1],
        'filename' => $m[2],
        'size_bytes' => (int) $m[3],
        'output' => trim($output),
    ];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentDeleteBackup(array $body): array
{
    $filename = (string) ($body['filename'] ?? '');

    if ($filename === '') {
        http_response_code(422);

        return ['success' => false, 'output' => 'Fichier requis'];
    }

    $output = agentRunBackupScript(dirname(__DIR__).'/scripts/backup-delete.sh', [$filename]);

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return ['success' => true, 'output' => trim($output)];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentRestoreBackup(array $body): array
{
    $filename = (string) ($body['filename'] ?? '');
    $database = (string) ($body['database'] ?? '');

    if ($filename === '') {
        http_response_code(422);

        return ['success' => false, 'output' => 'Fichier requis'];
    }

    $args = [$filename];
    if ($database !== '') {
        $args[] = $database;
    }

    $output = agentRunBackupScript(dirname(__DIR__).'/scripts/backup-restore.sh', $args);

    if (! preg_match('/OK:(.+)/', $output, $m)) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return [
        'success' => true,
        'database' => trim($m[1]),
        'output' => trim($output),
    ];
}
