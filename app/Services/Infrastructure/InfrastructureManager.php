<?php

declare(strict_types=1);

namespace App\Services\Infrastructure;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;

final class InfrastructureManager
{
    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
        private readonly ServerManager $servers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function firewallStatus(): array
    {
        return $this->runJsonScript('firewall-status.sh');
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function firewallPort(string $action, int $port): array
    {
        if (! in_array($action, ['open', 'close'], true) || $port < 1 || $port > 65535) {
            return ['success' => false, 'message' => 'Port ou action invalide.'];
        }

        $result = $this->scripts->run(
            base_path('agent/scripts/firewall-port.sh'),
            [$action, (string) $port],
        );

        $output = trim($result->output.$result->errorOutput);
        $success = $result->successful && str_starts_with($output, 'OK:');

        return [
            'success' => $success,
            'message' => $success ? "Port {$port} : {$action}." : ($output ?: 'Échec pare-feu.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sslCertificates(): array
    {
        $data = $this->runJsonScript('ssl-list.sh');

        if (array_is_list($data)) {
            return ['certificates' => $data];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function redisInfo(): array
    {
        return $this->runJsonScript('redis-info.sh');
    }

    /**
     * @return array<string, mixed>
     */
    public function nginxVhosts(): array
    {
        return $this->runJsonScript('nginx-vhosts.sh');
    }

    /**
     * @return array<string, mixed>
     */
    public function apacheStatus(): array
    {
        return $this->runJsonScript('apache-status.sh');
    }

    /**
     * @return array<string, mixed>
     */
    public function dnsStatus(): array
    {
        return $this->runJsonScript('dns-status.sh');
    }

    /**
     * @return array<string, mixed>
     */
    public function ftpStatus(): array
    {
        return $this->runJsonScript('ftp-status.sh');
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationsInventory(): array
    {
        return $this->runJsonScript('apps-inventory.sh');
    }

    /**
     * @return array<string, mixed>
     */
    public function clusterOverview(): array
    {
        $servers = $this->servers->all();

        return [
            'nodes' => $servers->map(fn (Server $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'ip' => $s->ip_address,
                'is_master' => (bool) $s->is_master,
                'status' => $s->status->value ?? (string) $s->status,
            ])->values()->all(),
            'count' => $servers->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runJsonScript(string $script): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return ['offline' => true, 'message' => 'Disponible sur le serveur Linux uniquement.'];
        }

        $result = $this->scripts->run(base_path('agent/scripts/'.$script));
        $output = trim($result->output);

        if (! str_starts_with($output, 'OK:')) {
            return [
                'error' => trim($output.$result->errorOutput) ?: 'Script infrastruture en échec.',
            ];
        }

        $json = substr($output, 3);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true) ?? [];

        return $decoded;
    }
}
