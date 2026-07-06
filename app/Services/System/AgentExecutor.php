<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class AgentExecutor implements SystemExecutorInterface
{
    public function __construct(
        private readonly Server $server,
    ) {}

    public function run(string $command, array $options = []): ProcessResult
    {
        $node = $this->server->primaryNode;

        if ($node === null) {
            return ProcessResult::failure('Aucun nœud configuré pour ce serveur.');
        }

        $start = microtime(true);
        $host = $node->host ?? $this->server->ip_address;
        $port = $node->port ?? 9100;

        try {
            $response = Http::timeout((int) ($options['timeout'] ?? 120))
                ->withToken($this->server->agent_token)
                ->post("http://{$host}:{$port}/api/v1/execute", [
                    'command' => $command,
                ]);

            $duration = microtime(true) - $start;

            if ($response->successful()) {
                return ProcessResult::success(
                    output: (string) $response->json('output', ''),
                    exitCode: (int) $response->json('exit_code', 0),
                    duration: $duration,
                );
            }

            return ProcessResult::failure(
                errorOutput: (string) $response->json('error', $response->body()),
                duration: $duration,
            );
        } catch (\Throwable $e) {
            return ProcessResult::failure(
                errorOutput: $e->getMessage(),
                duration: microtime(true) - $start,
            );
        }
    }

    public function runScript(string $path, array $args = []): ProcessResult
    {
        $escapedArgs = implode(' ', array_map('escapeshellarg', $args));

        return $this->run("bash ".escapeshellarg($path)." {$escapedArgs}");
    }

    public function runAsUser(string $user, string $command, array $options = []): ProcessResult
    {
        return $this->run("sudo -u ".escapeshellarg($user)." {$command}", $options);
    }
}
