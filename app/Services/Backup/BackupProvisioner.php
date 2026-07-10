<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Enums\BackupType;
use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class BackupProvisioner
{
    public function __construct(
        private readonly ServerManager $serverManager,
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    /**
     * @return array{success: bool, storage_path: string, filename: string, size_bytes: int, output: string}
     */
    public function create(Server $server, string $label, BackupType $type, ?string $target = null): array
    {
        $label = $this->sanitizeLabel($label);
        $target = $target !== null && $target !== '' ? $target : null;

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                $filename = "{$label}-{$type->value}-dev.sql.gz";

                return [
                    'success' => true,
                    'storage_path' => "/var/backups/obiora/{$filename}",
                    'filename' => $filename,
                    'size_bytes' => 1024,
                    'output' => "OK:/var/backups/obiora/{$filename}:{$filename}:1024:{$type->value}",
                ];
            }

            $args = [$type->value, $label];
            if ($target) {
                $args[] = $target;
            }

            $result = $this->runScript($server, base_path('agent/scripts/backup-create.sh'), $args, 600);

            return $this->parseCreateOutput($result['success'], $result['output'], $type);
        }

        return $this->remoteCreate($server, $label, $type, $target);
    }

    /**
     * @return array{success: bool, output: string}
     */
    public function delete(Server $server, string $filename): array
    {
        $filename = $this->sanitizeFilename($filename);

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'output' => 'OK:deleted (dev)'];
            }

            $result = $this->runScript($server, base_path('agent/scripts/backup-delete.sh'), [$filename]);

            return [
                'success' => $result['success'] && str_contains($result['output'], 'OK:'),
                'output' => $result['output'],
            ];
        }

        return $this->remoteDelete($server, $filename);
    }

    /**
     * @return array{success: bool, database: string, output: string}
     */
    public function restore(Server $server, string $filename, ?string $database = null): array
    {
        $filename = $this->sanitizeFilename($filename);

        if ($this->isLocal($server)) {
            if (PHP_OS_FAMILY !== 'Linux') {
                return ['success' => true, 'database' => $database ?? 'restored_db', 'output' => 'OK:restored_db (dev)'];
            }

            $args = [$filename];
            if ($database) {
                $args[] = $database;
            }

            $result = $this->runScript($server, base_path('agent/scripts/backup-restore.sh'), $args, 600);

            if (preg_match('/OK:(.+)/', $result['output'], $m)) {
                return ['success' => true, 'database' => trim($m[1]), 'output' => $result['output']];
            }

            return ['success' => false, 'database' => '', 'output' => $result['output']];
        }

        return $this->remoteRestore($server, $filename, $database);
    }

    private function isLocal(Server $server): bool
    {
        return $server->is_master || $server->type->value === 'local';
    }

    private function sanitizeLabel(string $label): string
    {
        $label = trim($label);

        if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $label)) {
            throw new InvalidArgumentException('Nom de sauvegarde invalide.');
        }

        return $label;
    }

    private function sanitizeFilename(string $filename): string
    {
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            throw new InvalidArgumentException('Nom de fichier invalide.');
        }

        return $filename;
    }

    /**
     * @param  list<string>  $args
     * @return array{success: bool, output: string}
     */
    private function runScript(Server $server, string $script, array $args = [], int $timeout = 120): array
    {
        $result = $this->scripts->run($script, $args, $timeout);

        return [
            'success' => $result->successful,
            'output' => trim($result->output.$result->errorOutput),
        ];
    }

    /**
     * @return array{success: bool, storage_path: string, filename: string, size_bytes: int, output: string}
     */
    private function parseCreateOutput(bool $success, string $output, BackupType $type): array
    {
        if ($success && preg_match('/OK:([^:]+):([^:]+):(\d+):/', $output, $m)) {
            return [
                'success' => true,
                'storage_path' => $m[1],
                'filename' => $m[2],
                'size_bytes' => (int) $m[3],
                'output' => $output,
            ];
        }

        if (! $result['success'] && $output === '') {
            $output = 'Le script de sauvegarde n\'a retourné aucune sortie (vérifiez sudoers, MariaDB et storage/logs/provisioning.log).';
        }

        return [
            'success' => false,
            'storage_path' => '',
            'filename' => '',
            'size_bytes' => 0,
            'output' => $output,
        ];
    }

    /**
     * @return array{success: bool, storage_path: string, filename: string, size_bytes: int, output: string}
     */
    private function remoteCreate(Server $server, string $label, BackupType $type, ?string $target): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/backups', [
                'label' => $label,
                'type' => $type->value,
                'target' => $target,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'storage_path' => '', 'filename' => '', 'size_bytes' => 0, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'storage_path' => (string) $response->json('storage_path', ''),
            'filename' => (string) $response->json('filename', ''),
            'size_bytes' => (int) $response->json('size_bytes', 0),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function remoteDelete(Server $server, string $filename): array
    {
        try {
            $response = $this->agentRequest($server, 'DELETE', '/api/v1/backups', ['filename' => $filename]);
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @return array{success: bool, database: string, output: string}
     */
    private function remoteRestore(Server $server, string $filename, ?string $database): array
    {
        try {
            $response = $this->agentRequest($server, 'POST', '/api/v1/backups/restore', [
                'filename' => $filename,
                'database' => $database,
            ], 600);
        } catch (\Throwable $e) {
            return ['success' => false, 'database' => '', 'output' => $e->getMessage()];
        }

        return [
            'success' => (bool) $response->json('success', false),
            'database' => (string) $response->json('database', ''),
            'output' => (string) $response->json('output', $response->body()),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function agentRequest(Server $server, string $method, string $uri, array $payload = [], int $timeout = 120): \Illuminate\Http\Client\Response
    {
        $node = $server->primaryNode;

        if ($node === null) {
            throw new InvalidArgumentException('Nœud agent introuvable.');
        }

        $host = $node->host ?? $server->ip_address;
        $port = $node->port ?? 9100;
        $url = "http://{$host}:{$port}{$uri}";

        $client = Http::timeout($timeout)->withToken($server->agent_token);

        return match ($method) {
            'POST' => $client->post($url, $payload),
            'DELETE' => $client->withBody(json_encode($payload), 'application/json')->delete($url),
            default => $client->get($url, $payload),
        };
    }
}
