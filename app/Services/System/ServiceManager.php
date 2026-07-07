<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Events\ServiceStateChanged;
use App\Jobs\ServiceActionJob;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Support\Realtime;
use Illuminate\Support\Facades\Http;

final class ServiceManager
{
    private const ALLOWED_ACTIONS = ['start', 'stop', 'restart', 'reload', 'enable', 'disable'];

    /** Services oneshot remplacés par leur timer dans l'UI. */
    private const TIMER_ALIASES = [
        'obiora-scheduler.service' => 'obiora-scheduler.timer',
    ];

    /** Timers affichés à la place des services oneshot. */
    private const PANEL_TIMERS = [
        'obiora-scheduler.timer' => 'ObiOra Panel Scheduler (exécution planifiée chaque minute)',
    ];

    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    /**
     * @return list<array{name: string, load: string, active: string, sub: string, description: string, unit_type?: string}>
     */
    public function list(?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return [];
        }

        if ($this->isLocal($server)) {
            $services = $this->filterManageableServices(
                $this->parseLocalList(
                    $this->serverManager->executorFor($server)->run(
                        'systemctl list-units --type=service --all --no-pager --no-legend'
                    )->output
                )
            );

            return $this->enrichWithTimers($services, $server);
        }

        return $this->fetchRemoteList($server);
    }

    /**
     * @return array{success: bool, output: string, async?: bool}
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

        $serviceName = $this->resolveUnitName($this->sanitizeServiceName($serviceName));

        if ($this->shouldRunAsync($serviceName, $action)) {
            ServiceActionJob::dispatch($server->id, $serviceName, $action);

            return [
                'success' => true,
                'async' => true,
                'output' => "Action « {$action} » lancée en arrière-plan sur {$serviceName} (évite une coupure du panel).",
            ];
        }

        return $this->runActionSync($serviceName, $action, $server);
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function runActionSync(string $serviceName, string $action, ?Server $server = null): array
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return ['success' => false, 'output' => 'Aucun serveur sélectionné'];
        }

        $serviceName = $this->resolveUnitName($this->sanitizeServiceName($serviceName));

        if ($this->isLocal($server)) {
            $script = base_path('agent/scripts/systemctl-action.sh');
            $result = $this->scripts->run($script, [$action, $serviceName], 120);

            $output = trim($result->output.$result->errorOutput);
            $response = [
                'success' => $result->successful || str_contains($output, 'OK:'),
                'output' => $output,
            ];
            $this->broadcastServiceState($server, $serviceName, $action, $response['success'], $response['output']);

            return $response;
        }

        $response = $this->remoteAction($server, $serviceName, $action);
        $this->broadcastServiceState($server, $serviceName, $action, $response['success'], $response['output']);

        return $response;
    }

    private function broadcastServiceState(
        Server $server,
        string $serviceName,
        string $action,
        bool $success,
        string $output,
    ): void {
        if (! Realtime::enabled()) {
            return;
        }

        event(new ServiceStateChanged($server->id, $serviceName, $action, $success, $output));
    }

    public function logs(string $serviceName, int $lines = 100, ?Server $server = null): string
    {
        $server ??= $this->serverManager->getCurrentServer();

        if ($server === null) {
            return '';
        }

        $serviceName = $this->resolveUnitName($this->sanitizeServiceName($serviceName));
        $lines = max(10, min($lines, 500));

        if ($this->isLocal($server)) {
            $script = base_path('agent/scripts/systemctl-logs.sh');
            $result = $this->scripts->run($script, [$serviceName, (string) $lines], 30);

            return trim($result->output.$result->errorOutput) ?: 'Aucun log récent pour cette unité.';
        }

        return $this->remoteLogs($server, $serviceName, $lines);
    }

    public function resolveUnitName(string $name): string
    {
        return self::TIMER_ALIASES[$name] ?? $name;
    }

    private function shouldRunAsync(string $serviceName, string $action): bool
    {
        if (! in_array($action, ['restart', 'stop'], true)) {
            return false;
        }

        $lower = strtolower($serviceName);

        foreach (['mariadb', 'mysqld', 'mysql', 'nginx', 'httpd', 'php-fpm', 'php8'] as $critical) {
            if (str_contains($lower, $critical)) {
                return true;
            }
        }

        return false;
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
     * @param  list<array{name: string, load: string, active: string, sub: string, description: string}>  $services
     * @return list<array{name: string, load: string, active: string, sub: string, description: string, unit_type?: string}>
     */
    private function enrichWithTimers(array $services, Server $server): array
    {
        $hidden = array_keys(self::TIMER_ALIASES);

        $services = array_values(array_filter(
            $services,
            fn (array $svc): bool => ! in_array($svc['name'], $hidden, true),
        ));

        $existing = array_column($services, 'name');

        foreach (self::PANEL_TIMERS as $timer => $description) {
            if (in_array($timer, $existing, true)) {
                continue;
            }

            $row = $this->fetchUnitRow($timer, $server);
            if ($row !== null) {
                $row['description'] = $description;
                $row['unit_type'] = 'timer';
                $services[] = $row;
            }
        }

        return $services;
    }

    /**
     * @return ?array{name: string, load: string, active: string, sub: string, description: string, unit_type?: string}
     */
    private function fetchUnitRow(string $unit, Server $server): ?array
    {
        $output = trim($this->serverManager->executorFor($server)->run(
            'systemctl list-units '.escapeshellarg($unit).' --all --no-pager --no-legend'
        )->output);

        if ($output === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $output, 5);
        if (count($parts) < 4) {
            return null;
        }

        return [
            'name' => $parts[0],
            'load' => $parts[1],
            'active' => $parts[2],
            'sub' => $parts[3],
            'description' => $parts[4] ?? '',
            'unit_type' => str_ends_with($unit, '.timer') ? 'timer' : 'service',
        ];
    }

    /**
     * @param  list<array{name: string, load: string, active: string, sub: string, description: string}>  $services
     * @return list<array{name: string, load: string, active: string, sub: string, description: string}>
     */
    private function filterManageableServices(array $services): array
    {
        return array_values(array_filter($services, function (array $svc): bool {
            return $this->isPanelService($svc['name']);
        }));
    }

    private function isPanelService(string $name): bool
    {
        $keywords = [
            'nginx', 'php-fpm', 'php', 'mariadb', 'mysqld', 'mysql', 'redis',
            'obiora', 'docker', 'fail2ban', 'supervisord', 'httpd', 'cron',
            'qbittorrent', 'deluge', 'rtorrent', 'transmission', 'emby', 'jellyfin',
            'plex', 'sonarr', 'radarr', 'netdata', 'vsftpd', 'postfix', 'dovecot',
            'openvpn', 'wireguard', 'memcached', 'nfs', 'smb',
        ];

        $lower = strtolower($name);

        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
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
