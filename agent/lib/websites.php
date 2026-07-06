<?php

declare(strict_types=1);

/**
 * Fonctions sites web pour l'agent ObiOra.
 */

/**
 * @return list<array<string, mixed>>
 */
function agentListWebsites(string $webRoot = '/var/www'): array
{
    if (PHP_OS_FAMILY !== 'Linux' || ! is_dir($webRoot)) {
        return [];
    }

    $sites = [];

    foreach (glob($webRoot.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $metaFile = $dir.'/.obiora.json';

        if (! is_file($metaFile)) {
            continue;
        }

        $meta = json_decode((string) file_get_contents($metaFile), true);

        if (! is_array($meta)) {
            continue;
        }

        $sites[] = $meta;
    }

    usort($sites, fn ($a, $b) => strcmp((string) ($a['domain'] ?? ''), (string) ($b['domain'] ?? '')));

    return $sites;
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentCreateWebsite(array $body): array
{
    $domain = (string) ($body['domain'] ?? '');
    $phpVersion = (string) ($body['php_version'] ?? '8.3');
    $webRoot = (string) ($body['web_root'] ?? '/var/www');

    $script = dirname(__DIR__).'/scripts/website-create.sh';

    if (! is_file($script)) {
        http_response_code(500);

        return ['success' => false, 'output' => 'Script introuvable'];
    }

    $cmd = 'bash '.escapeshellarg($script).' '
        .escapeshellarg($domain).' '
        .escapeshellarg($phpVersion).' '
        .escapeshellarg($webRoot).' 2>&1';

    $output = shell_exec($cmd) ?? '';

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    if (preg_match('/OK:(.+):(.+)/', $output, $m)) {
        return [
            'success' => true,
            'document_root' => trim($m[1]),
            'nginx_config_path' => trim($m[2]),
            'output' => trim($output),
        ];
    }

    return ['success' => true, 'output' => trim($output)];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentDeleteWebsite(array $body): array
{
    $domain = (string) ($body['domain'] ?? '');
    $webRoot = (string) ($body['web_root'] ?? '/var/www');
    $script = dirname(__DIR__).'/scripts/website-delete.sh';

    if ($domain === '' || ! is_file($script)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Paramètres invalides'];
    }

    $output = shell_exec(
        'bash '.escapeshellarg($script).' '.escapeshellarg($domain).' '.escapeshellarg($webRoot).' 2>&1'
    ) ?? '';

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
function agentIssueSsl(array $body): array
{
    $domain = (string) ($body['domain'] ?? '');
    $email = (string) ($body['email'] ?? '');
    $webRoot = (string) ($body['web_root'] ?? '/var/www');
    $script = dirname(__DIR__).'/scripts/website-ssl.sh';

    if ($domain === '' || $email === '' || ! is_file($script)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Paramètres invalides'];
    }

    $output = shell_exec(
        'bash '.escapeshellarg($script).' '
        .escapeshellarg($domain).' '
        .escapeshellarg($email).' '
        .escapeshellarg($webRoot).' 2>&1'
    ) ?? '';

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    $expires = null;

    if (preg_match('/OK:(.*)/', $output, $m)) {
        $raw = trim($m[1]);
        if ($raw !== '') {
            $expires = $raw;
        }
    }

    return [
        'success' => true,
        'expires_at' => $expires,
        'output' => trim($output),
    ];
}
