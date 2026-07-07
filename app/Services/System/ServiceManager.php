<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Facades\Http;

final class ServiceManager
{
    private const ALLOWED_ACTIONS = ['start', 'stop', 'restart', 'reload', 'enable', 'disable'];

    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    /**
     * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
     */
    public function list(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return [];
        }

        if ($this->isLocal($server)) {
            return $this->filterManageableServices(
                $this->parseLocalList(
                    $this->serverManager->executorFor($server)->run(
                        'systemctl list-units --type=service --all --no-pager --no-legend'
                    )->output
                )
            );
        }

        return $this->fetchRemoteList($server);
    }

  /**
     * @return array{success: bool, output: string}
     */
    public function action(string $serviceName, string $action, ?Server $server = null): array
    {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            return ['success' => false, 'output' => 'Action non autorisée'];
        }

        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'output' => 'Aucun serveur sélectionné'];
        }

        $serviceName = $this->sanitizeServiceName($serviceName);

        if ($this->isLocal($server)) {
            $script = base_path('agent/scripts/systemctl-action.sh');
            $result = $this->scripts->run($script, [$action, $serviceName], 60);

            return [
                'success' => $result->successful,
                'output' => trim($result->output.$result->errorOutput),
            ];
        }

        return $this->remoteAction($server, $serviceName, $action);
    }

    public function logs(string $serviceName, int $lines = 100, ?Server $server = null): string
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return '';
        }

        $serviceName = $this->sanitizeServiceName($serviceName);
        $lines = max(10, min($lines, 500));

        if ($this->isLocal($server)) {
            $script = base_path('agent/scripts/systemctl-logs.sh');
            $result = $this->scripts->run($script, [$serviceName, (string) $lines], 30);

            return trim($result->output.$result->errorOutput);
        }

        return $this->remoteLogs($server, $serviceName, $lines);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function sanitizeServiceName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9@._\-]/', '', $name) ?? $name;
    }

    /**
     * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
     */
    private function parseLocalList(string $output): array
    {
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
     * Masque les services systemd internes non gérables depuis le panel.
     *
     * @param  list<array{name: string, load: string, active: string, sub: string, description: string}>  $services
     * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
     */
    private function filterManageableServices(array $services): array
    {
        return array_values(array_filter($services, function (array $svc): bool {
            $name = $svc['name'];

            return ! preg_match('/^(systemd-|dbus-|dev-|sys-|dracut-|kmod-|user@|session-)/', $name);
        }));
    }

    /**
     * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
     */
    private function fetchRemoteList(Server $server): array
    {
        $node = $server->primaryNode;
        if ($node === null) {
            return [];
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;
            $response = Http::timeout(15)->withToken($server->agent_token)
                ->get("http://{$host}:{$port}/api/v1/services");

            return $response->successful() ? $response->json('data', []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteAction(Server $server, string $service, string $action): array
    {
        $node = $server->primaryNode;
        if ($node === null) {
            return ['success' => false, 'output' => 'Nœud introuvable'];
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;
            $response = Http::timeout(30)->withToken($server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/services/action", [
                    'service' => $service,
                    'action' => $action,
                ]);

            return [
                'success' => (bool) $response->json('success', false),
                'output' => (string) $response->json('output', $response->body()),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }
    }

    private function remoteLogs(Server $server, string $service, int $lines): string
    {
        $node = $server->primaryNode;
        if ($node === null) {
            return '';
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;
            $response = Http::timeout(15)->withToken($server->agent_token)
                ->get("http://{$host}:{$port}/api/v1/services/logs", [
                    'name' => $service,
                    'lines' => $lines,
                ]);

            return $response->successful() ? (string) $response->json('output', '') : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
