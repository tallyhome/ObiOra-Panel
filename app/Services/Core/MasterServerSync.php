<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use App\Models\Server;
use App\Models\ServerNode;
use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Garantit l'existence du serveur maître local et la cohérence agent.json ↔ base.
 */
final class MasterServerSync
{
    public function ensure(): Server
    {
        $existing = Server::query()->where('is_master', true)->first();

        $server = Server::query()->updateOrCreate(
            ['is_master' => true],
            [
                'name' => config('obiora.default_server.name', 'Local Server'),
                'hostname' => $this->detectHostname(),
                'ip_address' => $this->detectIpAddress(),
                'type' => ServerType::Local,
                'status' => ServerStatus::Online,
                'os_name' => PHP_OS_FAMILY,
                'agent_token' => $existing?->agent_token ?: Str::random(64),
                'last_seen_at' => now(),
            ],
        );

        ServerNode::query()->updateOrCreate(
            ['server_id' => $server->id, 'is_primary' => true],
            [
                'connection_type' => 'local',
                'host' => '127.0.0.1',
                'port' => 9100,
                'is_active' => true,
                'last_ping_at' => now(),
            ],
        );

        $this->ensureInstallationUuid();
        $this->syncAgentConfig($server);

        return $server->fresh() ?? $server;
    }

    public function ensureIfMissing(): ?Server
    {
        if (Server::query()->where('is_master', true)->exists()) {
            return Server::query()->where('is_master', true)->first();
        }

        try {
            return $this->ensure();
        } catch (\Throwable $exception) {
            Log::error('Impossible de créer le serveur maître', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function ensureInstallationUuid(): void
    {
        $existing = Setting::query()
            ->where('group', 'installation')
            ->where('key', 'uuid')
            ->value('value');

        $uuid = is_array($existing) ? ($existing['uuid'] ?? null) : null;
        $uuid = $uuid ?: config('obiora.installation_uuid') ?: env('OBIORA_INSTALLATION_UUID');

        if (! is_string($uuid) || $uuid === '') {
            $uuid = (string) Str::uuid();
        }

        Setting::query()->updateOrCreate(
            ['group' => 'installation', 'key' => 'uuid'],
            ['value' => ['uuid' => $uuid], 'is_public' => false],
        );
    }

    private function syncAgentConfig(Server $server): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        $token = (string) ($server->agent_token ?? '');

        if ($token === '') {
            return;
        }

        $configDir = base_path('agent/config');
        $configFile = $configDir.'/agent.json';

        File::ensureDirectoryExists($configDir);

        $payload = [
            'host' => '127.0.0.1',
            'port' => 9100,
            'token' => $token,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        if (is_file($configFile) && file_get_contents($configFile) === $encoded) {
            return;
        }

        File::put($configFile, $encoded);

        if (function_exists('posix_getpwnam')) {
            $obiora = posix_getpwnam('obiora');
            if (is_array($obiora)) {
                @chown($configFile, (int) $obiora['uid']);
                @chgrp($configFile, (int) $obiora['gid']);
            }
        }

        @chmod($configFile, 0600);
        \App\Support\AgentScripts::ensureExecutable();
    }

    private function detectHostname(): string
    {
        $hostname = gethostname();

        return is_string($hostname) && $hostname !== '' ? $hostname : 'localhost';
    }

    private function detectIpAddress(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $ip = trim((string) shell_exec("hostname -I 2>/dev/null | awk '{print $1}'") ?: '');

            if ($ip !== '') {
                return $ip;
            }
        }

        return '127.0.0.1';
    }
}
