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
 * @param  array<string, mixed>  $env
 */
function agentApplicationShellCommand(string $script, array $env = []): string
{
    $inner = 'bash '.escapeshellarg($script);

    if ($env !== []) {
        $exports = [];
        foreach ($env as $key => $valueB64) {
            if (! is_string($key) || ! preg_match('/^OBIORA_APP_[A-Z0-9_]+$/', $key)) {
                continue;
            }

            $value = base64_decode((string) $valueB64, true);
            if ($value === false) {
                continue;
            }

            $exports[] = 'export '.escapeshellarg($key).'='.escapeshellarg($value);
        }

        if ($exports !== []) {
            $inner = implode('; ', $exports).'; '.$inner;
        }
    }

    if (PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        return 'sudo -n bash -c '.escapeshellarg($inner);
    }

    return $inner;
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

    $realScript = realpath($script) ?: $script;
    $packagesRoot = realpath(agentPackagesRoot()) ?: agentPackagesRoot();

    if (! str_starts_with($realScript, $packagesRoot)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Chemin script non autorisé'];
    }

    if (! preg_match('#/packages/[a-z0-9-]+/(install|uninstall)\.sh$#', $realScript)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Script marketplace non autorisé'];
    }

    /** @var array<string, mixed> $env */
    $env = is_array($body['env'] ?? null) ? $body['env'] : [];
    $cmd = agentApplicationShellCommand($realScript, $env);
    $output = shell_exec($cmd.' 2>&1') ?? '';

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
function agentApplicationControl(array $body): array
{
    $action = (string) ($body['action'] ?? '');
    $runtimeType = (string) ($body['runtime_type'] ?? 'docker');
    $target = (string) ($body['target'] ?? '');

    if ($target === '' || ! preg_match('/^[a-zA-Z0-9@._-]+$/', $target)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Cible runtime invalide'];
    }

    if (! in_array($action, ['start', 'stop', 'restart'], true)) {
        http_response_code(422);

        return ['success' => false, 'output' => 'Action non autorisée'];
    }

    $scriptsDir = dirname(__DIR__).'/scripts';

    if ($runtimeType === 'docker') {
        $output = agentRunDockerScript($scriptsDir.'/docker-action.sh', [$target, $action]);
    } elseif ($runtimeType === 'systemd') {
        $output = agentRunDockerScript($scriptsDir.'/systemctl-action.sh', [$action, $target]);
    } else {
        http_response_code(422);

        return ['success' => false, 'output' => 'Runtime non supporté pour start/stop'];
    }

    return [
        'success' => str_contains($output, 'OK:'),
        'output' => trim($output),
    ];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentApplicationStatus(array $body): array
{
    $runtimeType = (string) ($body['runtime_type'] ?? 'docker');
    $target = (string) ($body['target'] ?? '');

    if ($target === '') {
        return ['status' => 'unknown'];
    }

    $scriptsDir = dirname(__DIR__).'/scripts';

    if ($runtimeType === 'docker') {
        $output = trim(agentRunDockerScript($scriptsDir.'/docker-status.sh', [$target]));
        if (str_contains($output, 'STATUS:running')) {
            return ['status' => 'running'];
        }
        if (str_contains($output, 'STATUS:stopped')) {
            return ['status' => 'stopped'];
        }
        if (str_contains($output, 'STATUS:not_found')) {
            return ['status' => 'not_found'];
        }

        return ['status' => 'unknown'];
    }

    if ($runtimeType === 'systemd') {
        $output = trim(agentRunDockerScript($scriptsDir.'/systemctl-action.sh', ['is-active', $target]));
        if (preg_match('/\bactive\b/', $output)) {
            return ['status' => 'running'];
        }
        if (preg_match('/\b(inactive|failed|dead)\b/', $output)) {
            return ['status' => 'stopped'];
        }

        return ['status' => 'unknown'];
    }

    return ['status' => 'installed'];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function agentApplicationLogs(array $body): array
{
    $runtimeType = (string) ($body['runtime_type'] ?? 'docker');
    $target = (string) ($body['target'] ?? '');
    $lines = max(10, min(500, (int) ($body['lines'] ?? 100)));

    if ($target === '') {
        return ['output' => 'Cible invalide'];
    }

    $scriptsDir = dirname(__DIR__).'/scripts';

    if ($runtimeType === 'docker') {
        $output = agentRunDockerScript($scriptsDir.'/docker-logs.sh', [$target, (string) $lines]);
    } elseif ($runtimeType === 'systemd') {
        $output = agentRunDockerScript($scriptsDir.'/systemctl-logs.sh', [$target, (string) $lines]);
    } else {
        return ['output' => 'Logs indisponibles pour ce type d\'application'];
    }

    return ['output' => trim($output) ?: 'Aucun log.'];
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
