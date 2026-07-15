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

        if (isset($options['argv']) && is_array($options['argv']) && $options['argv'] !== []) {
            /** @var list<string> $argv */
            $argv = array_values($options['argv']);
            $result = Process::timeout($timeout)->run($argv);
            $loggedCommand = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $argv));
        } else {
            $result = Process::timeout($timeout)->run($command);
            $loggedCommand = $command;
        }

        $duration = microtime(true) - $start;

        Log::channel('provisioning')->info('Command executed', [
            'command' => $loggedCommand,
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
