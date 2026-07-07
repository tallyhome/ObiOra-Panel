<?php

declare(strict_types=1);

/**
 * Ajoute ou met à jour manifest.runtime sans écraser install.options ni scripts custom.
 *
 * Usage: php tools/sync-package-runtimes.php
 */

$root = dirname(__DIR__);
$apps = require $root.'/tools/swizzin-apps.php';

$servicePorts = [
    'nginx' => 80,
    'shellinabox' => 4200,
    'netdata' => 19999,
    'webmin' => 10000,
    'vsftpd' => 21,
    'pure-ftpd' => 21,
    'x2go' => 22,
];

$updated = 0;

foreach ($apps as $app) {
    $slug = (string) $app['slug'];
    $type = (string) ($app['type'] ?? 'docker');

    if ($type === 'skip') {
        continue;
    }

    $manifestPath = $root.'/packages/'.$slug.'/manifest.json';

    if (! is_file($manifestPath)) {
        continue;
    }

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    if (($manifest['runtime']['type'] ?? null) === 'systemd' && isset($manifest['install']['options'])) {
        // Manifeste custom (vsftpd, filebrowser, qbittorrent…) — ne pas écraser.
        continue;
    }

    $runtime = match ($type) {
        'docker' => [
            'type' => 'docker',
            'container' => 'obiora-'.$slug,
            'port' => (int) $app['port'],
            'url' => buildUrl($slug, (int) $app['port']),
            'usage' => defaultUsage($app),
        ],
        'apt' => [
            'type' => 'systemd',
            'service' => (string) ($app['package'] ?? $slug),
            'port' => $servicePorts[$slug] ?? null,
            'url' => isset($servicePorts[$slug]) ? buildUrl($slug, $servicePorts[$slug]) : null,
            'usage' => defaultUsage($app),
        ],
        'script' => buildScriptRuntime($app, $servicePorts),
        'curl' => [
            'type' => 'binary',
            'usage' => defaultUsage($app),
        ],
        'native' => $manifest['runtime'] ?? [
            'type' => 'docker',
            'container' => 'obiora-'.$slug,
            'usage' => defaultUsage($app),
        ],
        default => null,
    };

    if ($runtime === null) {
        continue;
    }

    $runtime = array_filter($runtime, static fn ($value): bool => $value !== null && $value !== '');

    if (($manifest['runtime'] ?? []) === $runtime) {
        continue;
    }

    $manifest['runtime'] = $runtime;
    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );
    echo "UPDATED: {$slug}\n";
    $updated++;
}

echo "\nTerminé : {$updated} manifest(s) mis à jour.\n";

/**
 * @param  array<string, mixed>  $app
 */
function buildScriptRuntime(array $app, array $servicePorts): array
{
    $slug = (string) $app['slug'];
    $service = (string) ($app['service'] ?? $slug);

    if (in_array($service, ['letsencrypt'], true)) {
        return [
            'type' => 'binary',
            'usage' => defaultUsage($app),
        ];
    }

    return [
        'type' => 'systemd',
        'service' => $service,
        'port' => $servicePorts[$slug] ?? null,
        'url' => isset($servicePorts[$slug]) ? buildUrl($slug, $servicePorts[$slug]) : null,
        'usage' => defaultUsage($app),
    ];
}

function buildUrl(string $slug, int $port): string
{
    if ($slug === 'webmin') {
        return 'https://{host}:'.$port;
    }

    if ($slug === 'nextcloud') {
        return 'https://{host}:'.$port;
    }

    if ($slug === 'vsftpd' || $slug === 'pure-ftpd') {
        return 'ftp://{host}:'.$port;
    }

    return 'http://{host}:'.$port;
}

/**
 * @param  array<string, mixed>  $app
 */
function defaultUsage(array $app): string
{
    return (string) ($app['description'] ?? $app['name'] ?? $app['slug']);
}
