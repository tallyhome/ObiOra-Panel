<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

final class LocalExecutor implements SystemExecutorInterface
{
    public function run(string $command, array $options = []): ProcessResult
    {
        $timeout = (int) ($options['timeout'] ?? 120);
        $start = microtime(true);

        $result = Process::timeout($timeout)->run($command);

        $duration = microtime(true) - $start;

        Log::channel('provisioning')->info('Command executed', [
            'command' => $command,
            'exit_code' => $result->exitCode(),
            'duration' => $duration,
        ]);

        return new ProcessResult(
            successful: $result->successful(),
            exitCode: $result->exitCode(),
            output: $result->output(),
            errorOutput: $result->errorOutput(),
            duration: $duration,
        );
    }

    public function runScript(string $path, array $args = []): ProcessResult
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Script not found: {$path}");
        }

        $escapedArgs = implode(' ', array_map('escapeshellarg', $args));

        return $this->run("bash ".escapeshellarg($path)." {$escapedArgs}");
    }

    public function runAsUser(string $user, string $command, array $options = []): ProcessResult
    {
        return $this->run("sudo -u ".escapeshellarg($user)." {$command}", $options);
    }
}
