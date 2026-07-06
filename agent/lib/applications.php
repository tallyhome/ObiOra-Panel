<?php

declare(strict_types=1);

/**
 * Installation applications catalogue ObiOra (packages/).
 */

function agentPackagesRoot(): string
{
    return dirname(__DIR__, 2).'/packages';
}

/**
 * @return array<string, mixed>|null
 */
function agentFindPackageManifest(string $slug): ?array
{
    $path = agentPackagesRoot().'/'.$slug.'/manifest.json';

    if (! is_file($path)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentApplicationAction(array $body, string $action): array
{
    $slug = (string) ($body['slug'] ?? '');

    if ($slug === '' || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Slug invalide'];
    }

    $manifest = agentFindPackageManifest($slug);

    if ($manifest === null) {
        http_response_code(404);

        return ['success' => false, 'output' => 'Package introuvable'];
    }

    $scriptName = $manifest['scripts'][$action] ?? "{$action}.sh";
    $script = agentPackagesRoot().'/'.$slug.'/'.$scriptName;

    if (! is_file($script)) {
        http_response_code(404);

        return ['success' => false, 'output' => "Script {$action} introuvable"];
    }

    $cmd = 'bash '.escapeshellarg($script);

    if (PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        $cmd = 'sudo -n '.$cmd;
    }

    $output = shell_exec($cmd.' 2>&1') ?? '';

    if (! str_contains($output, 'OK:')) {
        http_response_code(422);

        return ['success' => false, 'output' => trim($output)];
    }

    return ['success' => true, 'output' => trim($output)];
}

/**
 * @return list<array<string, mixed>>
 */
function agentListPackages(): array
{
    $root = agentPackagesRoot();
    $packages = [];

    if (! is_dir($root)) {
        return [];
    }

    foreach (glob($root.'/*/manifest.json') ?: [] as $manifestPath) {
        $data = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($data)) {
            continue;
        }

        $packages[] = $data;
    }

    usort($packages, fn ($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

    return $packages;
}
