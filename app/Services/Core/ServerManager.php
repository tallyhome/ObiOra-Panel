<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SystemExecutorInterface;
use App\Enums\ServerStatus;
use App\Enums\ServerType;
use App\Models\Server;
use App\Models\ServerNode;
use App\Services\System\AgentExecutor;
use App\Services\System\LocalExecutor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class ServerManager
{
    public function __construct(
        private readonly LocalExecutor $localExecutor,
    ) {}

    public function getCurrentServer(): ?Server
    {
        $id = session('current_server_id');

        if ($id) {
            return Server::query()->find($id);
        }

        return Server::query()->where('is_master', true)->first();
    }

    public function setCurrentServer(Server $server): void
    {
        session(['current_server_id' => $server->id]);
    }

    /**
     * @return Collection<int, Server>
     */
    public function all(): Collection
    {
        return Server::query()->orderByDesc('is_master')->orderBy('name')->get();
    }

    public function executorFor(Server $server): SystemExecutorInterface
    {
        if ($server->is_master || $server->type === ServerType::Local) {
            return $this->localExecutor;
        }

        return new AgentExecutor($server);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRemote(array $data): Server
    {
        $token = (string) ($data['agent_token'] ?? '');

        if (strlen($token) < 32) {
            throw new InvalidArgumentException('La clé API slave est invalide (min. 32 caractères).');
        }

        $server = Server::query()->create([
            'name' => $data['name'],
            'hostname' => $data['hostname'] ?? $data['ip_address'],
            'ip_address' => $data['ip_address'],
            'type' => $data['type'] ?? ServerType::Vps,
            'status' => ServerStatus::Pending,
            'is_master' => false,
            'os_name' => $data['os_name'] ?? null,
            'agent_token' => $token,
        ]);

        ServerNode::query()->create([
            'server_id' => $server->id,
            'connection_type' => 'agent',
            'host' => $data['ip_address'],
            'port' => (int) ($data['agent_port'] ?? 9100),
            'is_primary' => true,
            'is_active' => true,
            'credentials' => ['token' => $token],
        ]);

        $this->ping($server);

        return $server->fresh(['nodes']) ?? $server;
    }

    public function ping(Server $server): bool
    {
        if ($server->is_master || $server->type === ServerType::Local) {
            $ip = $this->detectLocalIp();
            $hostname = gethostname() ?: $server->hostname;

            $server->update([
                'status' => ServerStatus::Online,
                'last_seen_at' => now(),
                'hostname' => $hostname,
                'ip_address' => $ip ?: $server->ip_address,
                'os_name' => PHP_OS_FAMILY,
            ]);
            $server->primaryNode?->update(['last_ping_at' => now()]);

            return true;
        }

        $node = $server->primaryNode;

        if ($node === null) {
            $server->update(['status' => ServerStatus::Error]);

            return false;
        }

        try {
            $host = $node->host ?? $server->ip_address;
            $port = $node->port ?? 9100;

            $response = Http::timeout(10)
                ->withToken($server->agent_token)
                ->get("http://{$host}:{$port}/api/v1/ping");

            if ($response->successful()) {
                $server->update([
                    'status' => ServerStatus::Online,
                    'last_seen_at' => now(),
                    'hostname' => $response->json('hostname', $server->hostname),
                    'ip_address' => $response->json('ip', $server->ip_address),
                    'os_name' => $response->json('os', $server->os_name),
                ]);
                $node->update(['last_ping_at' => now()]);

                return true;
            }
        } catch (\Throwable) {
            //
        }

        $server->update(['status' => ServerStatus::Offline]);

        return false;
    }

    public function delete(Server $server): void
    {
        if ($server->is_master) {
            throw new \RuntimeException('Impossible de supprimer le serveur maître.');
        }

        $server->delete();
    }

    private function detectLocalIp(): ?string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }

        $output = shell_exec('hostname -I 2>/dev/null') ?: '';
        $ip = trim(explode(' ', trim($output))[0] ?? '');

        if ($ip === '' || str_starts_with($ip, '127.')) {
            return null;
        }

        return $ip;
    }
}
