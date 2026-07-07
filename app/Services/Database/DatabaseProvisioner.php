<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class DatabaseProvisioner
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    /**
     * @return list<string>
     */
    public function listOnServer(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return [];
        }

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['dev_example_db'];
            }

            $script = base_path('agent/scripts/mysql-list.sh');
            $result = $this->scripts->run($script, []);

            return $this->parseListOutput($result->successful, trim($result->output.$result->errorOutput));
        }

        return $this->remoteList($server);
    }

    /**
     * @return array{success: bool, username: string, password: string, docker_host: string, output: string}
     */
    public function create(Server $server, string $name, ?string $username = null, ?string $password = null): array
    {
        $name = $this->sanitizeName($name);
        $username = $username !== null && $username !== '' ? $this->sanitizeUser($username) : "{$name}_user";

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                $pass = $password ?: bin2hex(random_bytes(12));

                return [
                    'success' => true,
                    'username' => $username,
                    'password' => $pass,
                    'docker_host' => '172.17.0.1',
                    'output' => "OK:{$name}:{$username}:{$pass} (dev stub)",
                ];
            }

            $script = base_path('agent/scripts/mysql-create.sh');
            $args = [$name, $username];
            if ($password) {
                $args[] = $password;
            }

            $result = $this->scripts->run($script, $args);

            return $this->parseCreateOutput($result->successful, trim($result->output.$result->errorOutput));
        }

        return $this->remoteCreate($server, $name, $username, $password);
    }

    /**
     * @return array{success: bool, docker_host: string, output: string}
     */
    public function grantDockerAccess(Server $server, string $username, string $password, string $database): array
    {
        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'docker_host' => '172.17.0.1', 'output' => 'OK (dev stub)'];
            }

            $script = base_path('agent/scripts/mysql-grant-docker.sh');
            $result = $this->scripts->run($script, [$username, $password, $database]);

            if ($result->successful && preg_match('/OK:[^:]+:(.++)/', trim($result->output), $m)) {
                return [
                    'success' => true,
                    'docker_host' => trim($m[1]),
                    'output' => trim($result->output),
                ];
            }

            return [
                'success' => false,
                'docker_host' => '172.17.0.1',
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return ['success' => false, 'docker_host' => '', 'output' => 'Non supporté sur serveur distant.'];
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function delete(Server $server, string $name, ?string $username = null): array
    {
        $name = $this->sanitizeName($name);
        $username = $username ? $this->sanitizeUser($username) : null;

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => 'Suppression simulée (dev)'];
            }

            $script = base_path('agent/scripts/mysql-delete.sh');
            $args = [$name];
            if ($username) {
                $args[] = $username;
            }

            $result = $this->scripts->run($script, $args);

            return [
                'success' => $result->successful,
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return $this->remoteDelete($server, $name, $username);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function sanitizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $reserved = config('obiora.databases.reserved_names', []);

        if (! preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
            throw new InvalidArgumentException('Nom de base invalide (lettres, chiffres, underscore).');
        }

        if (in_array($name, $reserved, true)) {
            throw new InvalidArgumentException("Le nom « {$name} » est réservé.");
        }

        return $name;
    }

    private function sanitizeUser(string $user): string
    {
        if (! preg_match('/^[a-z0-9_]{1,32}$/', $user)) {
            throw new InvalidArgumentException('Nom utilisateur invalide.');
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function parseListOutput(bool $success, string $output): array
    {
        if (! $success || ! str_starts_with($output, 'OK:')) {
            return [];
        }

        $payload = substr($output, 3);

        if ($payload === '') {
            return [];
        }

        return array_values(array_filter(explode(',', $payload)));
    }

    /**
     * @return array{success: bool, username: string, password: string, docker_host: string, output: string}
     */
    private function parseCreateOutput(bool $success, string $output): array
    {
        if ($success && preg_match('/OK:([^:]+):([^:]+):([^:]+)(?::(.+))?/', $output, $m)) {
            return [
                'success' => true,
                'username' => trim($m[2]),
                'password' => trim($m[3]),
                'docker_host' => trim($m[4] ?? '172.17.0.1'),
                'output' => $output,
            ];
        }

        return [
            'success' => false,
            'username' => '',
            'password' => '',
            'docker_host' => '',
            'output' => $output,
        ];
    }

    /**
     * @return list<string>
     */
    private function remoteList(Server $server): array
    {
        try {
            $response = $this->agentRequest($server, 'GET', '/api/v1/databases');
        } catch (\Throwable) {
            return [];
        }

        return $response->successful() ? $response->json('data', []) : [];
    }

    /**
     * @return array{success: bool, username: string, password: string, output: string}
     */
    private function remoteCreate(Server $server, string $name, string $username, ?string $password): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/databases', [
                'name' => $name,
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'username' => '', 'password' => '', 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'username' => (string) $response->json('username', ''),
            'password' => (string) $response->json('password', ''),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteDelete(Server $server, string $name, ?string $username): array
    {
        try {
            $response = $this->agentRequest($server, 'DELETE', '/api/v1/databases', [
                'name' => $name,
                'username' => $username,
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
