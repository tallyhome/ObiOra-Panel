<?php

declare(strict_types=1);

namespace App\Services\Docker;

use App\Jobs\DockerInstallJob;
use App\Jobs\DockerUninstallJob;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class DockerManager
{
    private const ALLOWED_ACTIONS = ['start', 'stop', 'restart', 'remove'];

    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    /**
     * @return array{installed: bool, version: ?string, running: int, total: int, images: int, error?: string}
     */
    public function info(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['installed' => false, 'version' => null, 'running' => 0, 'total' => 0, 'images' => 0];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return [
                    'installed' => true,
                    'version' => 'dev-stub',
                    'running' => 1,
                    'total' => 2,
                    'images' => 3,
                ];
            }

            $result = $this->runScript(base_path('agent/scripts/docker-info.sh'));

            return $this->parseInfoOutput($result['success'], $result['output']);
        }

        return $this->remoteInfo($server);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function installDocker(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'message' => 'Aucun serveur sélectionné.'];
        }

        if (! $this->isLocal($server)) {
            return ['success' => false, 'message' => 'Installation Docker disponible uniquement sur le serveur local.'];
        }

        if (PHP_OS_FAMILY !== 'Linux') {
            return ['success' => false, 'message' => 'Installation Docker non supportée sur cet environnement.'];
        }

        $status = \Illuminate\Support\Facades\Cache::get('obiora_progress:docker_install');
        if (is_array($status) && ($status['running'] ?? false)) {
            return ['success' => false, 'message' => 'Une installation Docker est déjà en cours.'];
        }

        \Illuminate\Support\Facades\Cache::put('obiora_progress:docker_install', [
            'progress' => 2,
            'message' => 'Mise en file d\'attente…',
            'running' => true,
            'success' => null,
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        DockerInstallJob::dispatch();

        return ['success' => true, 'message' => 'Installation Docker lancée en arrière-plan.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function uninstallDocker(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'message' => 'Aucun serveur sélectionné.'];
        }

        if (! $this->isLocal($server)) {
            return ['success' => false, 'message' => 'Désinstallation Docker disponible uniquement sur le serveur local.'];
        }

        if (PHP_OS_FAMILY !== 'Linux') {
            return ['success' => false, 'message' => 'Désinstallation Docker non supportée sur cet environnement.'];
        }

        $info = $this->info($server);
        if (! ($info['installed'] ?? false)) {
            return ['success' => false, 'message' => 'Docker n\'est pas installé sur ce serveur.'];
        }

        foreach (['docker_install', 'docker_uninstall'] as $key) {
            $status = \Illuminate\Support\Facades\Cache::get("obiora_progress:{$key}");
            if (is_array($status) && ($status['running'] ?? false)) {
                return ['success' => false, 'message' => 'Une opération Docker est déjà en cours.'];
            }
        }

        \Illuminate\Support\Facades\Cache::put('obiora_progress:docker_uninstall', [
            'progress' => 2,
            'message' => 'Mise en file d\'attente…',
            'running' => true,
            'success' => null,
            'updated_at' => now()->toIso8601String(),
        ], 3600);

        DockerUninstallJob::dispatch();

        return ['success' => true, 'message' => 'Désinstallation Docker lancée en arrière-plan.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function runUninstallScript(): array
    {
        $result = $this->scripts->run(base_path('agent/scripts/docker-uninstall.sh'), [], 600);

        $output = trim($result->output."\n".$result->errorOutput);

        if ($result->successful && str_contains($output, 'OK:')) {
            return ['success' => true, 'message' => $this->extractOkMessage($output, 'Docker désinstallé')];
        }

        return ['success' => false, 'message' => $output !== '' ? $output : 'Échec désinstallation Docker'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function runInstallScript(): array
    {
        $result = $this->scripts->run(base_path('agent/scripts/docker-install.sh'), [], 900);

        $output = trim($result->output."\n".$result->errorOutput);

        if ($result->successful && str_contains($output, 'OK:')) {
            return ['success' => true, 'message' => $this->extractOkMessage($output, 'Docker installé')];
        }

        return ['success' => false, 'message' => $output !== '' ? $output : 'Échec installation Docker'];
    }

    private function extractOkMessage(string $output, string $fallback): string
    {
        if (preg_match('/^OK:(.+)$/m', $output, $matches)) {
            $message = trim($matches[1]);

            return $message !== '' ? $message : $fallback;
        }

        return $fallback;
    }

    /**
     * @return list<array{id: string, name: string, image: string, status: string, ports: string}>
     */
    public function containers(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return [];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return [[
                    'id' => 'abc123',
                    'name' => 'dev-nginx',
                    'image' => 'nginx:alpine',
                    'status' => 'Up 2 hours',
                    'ports' => '0.0.0.0:8080->80/tcp',
                ]];
            }

            $result = $this->runScript(base_path('agent/scripts/docker-containers.sh'));

            return $this->parseRows($result['output'], ['id', 'name', 'image', 'status', 'ports']);
        }

        return $this->remoteContainers($server);
    }

    /**
     * @return list<array{id: string, repository: string, tag: string, size: string}>
     */
    public function images(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return [];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return [[
                    'id' => 'def456',
                    'repository' => 'nginx',
                    'tag' => 'alpine',
                    'size' => '50MB',
                ]];
            }

            $result = $this->runScript(base_path('agent/scripts/docker-images.sh'));

            return $this->parseRows($result['output'], ['id', 'repository', 'tag', 'size']);
        }

        return $this->remoteImages($server);
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function containerAction(string $container, string $action, ?Server $server = null): array
    {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            return ['success' => false, 'output' => 'Action non autorisée'];
        }

        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'output' => 'Aucun serveur sélectionné'];
        }

        $container = $this->sanitizeContainerRef($container);

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => "OK:{$action} (dev)"];
            }

            $result = $this->runScript(base_path('agent/scripts/docker-action.sh'), [$container, $action]);

            return [
                'success' => $result['success'] && str_contains($result['output'], 'OK:'),
                'output' => $result['output'],
            ];
        }

        return $this->remoteContainerAction($server, $container, $action);
    }

    public function containerLogs(string $container, int $lines = 100, ?Server $server = null): string
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return '';
        }

        $container = $this->sanitizeContainerRef($container);
        $lines = max(10, min($lines, 500));

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return "Logs simulés pour {$container}";
            }

            $result = $this->runScript(base_path('agent/scripts/docker-logs.sh'), [$container, (string) $lines]);
            $output = preg_replace('/\nOK$/', '', $result['output']) ?? $result['output'];

            return trim($output);
        }

        return $this->remoteContainerLogs($server, $container, $lines);
    }

    /**
     * @return array{success: bool, container_id: string, output: string}
     */
    public function runContainer(string $image, ?string $name = null, ?string $ports = null, ?Server $server = null): array
    {
        $image = $this->sanitizeImageRef($image);
        $name = $name ? $this->sanitizeContainerRef($name) : null;

        if ($ports !== null && $ports !== '' && ! preg_match('/^[0-9]+:[0-9]+$/', $ports)) {
            throw new InvalidArgumentException('Mapping de ports invalide (ex: 8080:80).');
        }

        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'container_id' => '', 'output' => 'Aucun serveur sélectionné'];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'container_id' => 'dev123', 'output' => 'OK:dev123'];
            }

            $args = [$image];
            if ($name) {
                $args[] = $name;
            }
            if ($ports) {
                $args[] = $ports;
            }

            $result = $this->runScript(base_path('agent/scripts/docker-run.sh'), $args);

            if (preg_match('/OK:(.+)/', $result['output'], $m)) {
                return ['success' => true, 'container_id' => trim($m[1]), 'output' => $result['output']];
            }

            return ['success' => false, 'container_id' => '', 'output' => $result['output']];
        }

        return $this->remoteRunContainer($server, $image, $name, $ports);
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function removeImage(string $image, ?Server $server = null): array
    {
        $image = $this->sanitizeImageRef($image);
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'output' => 'Aucun serveur sélectionné'];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => 'OK:removed (dev)'];
            }

            $result = $this->runScript(base_path('agent/scripts/docker-rmi.sh'), [$image]);

            return [
                'success' => $result['success'] && str_contains($result['output'], 'OK:'),
                'output' => $result['output'],
            ];
        }

        return $this->remoteRemoveImage($server, $image);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function sanitizeContainerRef(string $ref): string
    {
        if (preg_match('/^[a-f0-9]{12,64}$/', $ref)) {
            return $ref;
        }

        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/', $ref)) {
            return $ref;
        }

        throw new InvalidArgumentException('Référence conteneur invalide.');
    }

    private function sanitizeImageRef(string $ref): string
    {
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._\/:@-]{0,255}$/', $ref)) {
            throw new InvalidArgumentException('Référence image invalide.');
        }

        return $ref;
    }

    /**
     * @param  list<string>  $args
     * @return array{success: bool, output: string}
     */
    private function runScript(string $script, array $args = []): array
    {
        $result = $this->scripts->run($script, $args);

        return [
            'success' => $result->successful,
            'output' => trim($result->output.$result->errorOutput),
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return list<array<string, string>>
     */
    private function parseRows(string $output, array $fields): array
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
     * @return array{installed: bool, version: ?string, running: int, total: int, images: int, error?: string}
     */
    private function parseInfoOutput(bool $success, string $output): array
    {
        if ($success && str_starts_with($output, 'OK:')) {
            $parts = explode(':', $output, 5);

            return [
                'installed' => true,
                'version' => $parts[1] ?? null,
                'running' => (int) ($parts[2] ?? 0),
                'total' => (int) ($parts[3] ?? 0),
                'images' => (int) ($parts[4] ?? 0),
            ];
        }

        return [
            'installed' => false,
            'version' => null,
            'running' => 0,
            'total' => 0,
            'images' => 0,
            'error' => $output,
        ];
    }

    /**
     * @return array{installed: bool, version: ?string, running: int, total: int, images: int, error?: string}
     */
    private function remoteInfo(Server $server): array
    {
        try {
            $response = $this->agentRequest($server, 'GET', '/api/v1/docker/info');
        } catch (\Throwable $e) {
            return ['installed' => false, 'version' => null, 'running' => 0, 'total' => 0, 'images' => 0, 'error' => $e->getMessage()];
        }

        return $response->json('data', ['installed' => false, 'version' => null, 'running' => 0, 'total' => 0, 'images' => 0]);
    }

    /**
     * @return list<array{id: string, name: string, image: string, status: string, ports: string}>
     */
    private function remoteContainers(Server $server): array
    {
        try {
            $response = $this->agentRequest($server, 'GET', '/api/v1/docker/containers');
        } catch (\Throwable) {
            return [];
        }

        return $response->successful() ? $response->json('data', []) : [];
    }

    /**
     * @return list<array{id: string, repository: string, tag: string, size: string}>
     */
    private function remoteImages(Server $server): array
    {
        try {
            $response = $this->agentRequest($server, 'GET', '/api/v1/docker/images');
        } catch (\Throwable) {
            return [];
        }

        return $response->successful() ? $response->json('data', []) : [];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteContainerAction(Server $server, string $container, string $action): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/docker/containers/action', [
                'container' => $container,
                'action' => $action,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    private function remoteContainerLogs(Server $server, string $container, int $lines): string
    {
        try {
            $response = $this->agentRequest($server, 'GET', '/api/v1/docker/containers/logs', [
                'container' => $container,
                'lines' => $lines,
            ]);
        } catch (\Throwable) {
            return '';
        }

        return $response->successful() ? (string) $response->json('output', '') : '';
    }

    /**
     * @return array{success: bool, container_id: string, output: string}
     */
    private function remoteRunContainer(Server $server, string $image, ?string $name, ?string $ports): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/docker/containers/run', [
                'image' => $image,
                'name' => $name,
                'ports' => $ports,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'container_id' => '', 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'container_id' => (string) $response->json('container_id', ''),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteRemoveImage(Server $server, string $image): array
    {
        try {
            $response = $this->agentRequest($server, 'DELETE', '/api/v1/docker/images', [
                'image' => $image,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function agentRequest(Server $server, string $method, string $uri, array $payload = []): \Illuminate\Http\Client\Response
    {
        $node = $server->primaryNode;

        if ($node === null) {
            throw new InvalidArgumentException('Nœud agent introuvable.');
        }

        $host = $node->host ?? $server->ip_address;
        $port = $node->port ?? 9100;
        $url = "http://{$host}:{$port}{$uri}";

        $client = Http::timeout(120)->withToken($server->agent_token);

        return match ($method) {
            'POST' => $client->post($url, $payload),
            'DELETE' => $client->withBody(json_encode($payload), 'application/json')->delete($url),
            default => $client->get($url, $payload),
        };
    }
}
