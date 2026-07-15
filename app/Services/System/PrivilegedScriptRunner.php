<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Contracts\SystemExecutorInterface;
use App\DTOs\ProcessResult;

final class PrivilegedScriptRunner
{
    public function __construct(
        private readonly SystemExecutorInterface $executor,
    ) {}

    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     */
    public function run(string $script, array $args = [], int $timeout = 120, array $env = []): ProcessResult
    {
        if (! is_file($script)) {
            return ProcessResult::failure("Script introuvable: {$script}", 127);
        }

        $scriptPath = realpath($script) ?: $script;

        if ($this->needsSudo()) {
            $argv = array_merge(['sudo', '-n', $scriptPath], $args);

            return $this->executor->run('', ['timeout' => $timeout, 'argv' => $argv]);
        }

        if ($env !== []) {
            $envPrefix = implode(' ', array_map(
                fn (string $key, string $value): string => $key.'='.escapeshellarg($value),
                array_keys($env),
                array_values($env),
            ));
            $command = 'env '.$envPrefix.' '.escapeshellarg($scriptPath);
            if ($args !== []) {
                $command .= ' '.implode(' ', array_map('escapeshellarg', $args));
            }

            return $this->executor->run($command, ['timeout' => $timeout]);
        }

        $argv = array_merge([$scriptPath], $args);

        return $this->executor->run('', ['timeout' => $timeout, 'argv' => $argv]);
    }

    /**
     * Exécute une commande shell (systemctl, etc.) — pas un chemin de script seul.
     *
     * @param  array<string, string>  $env
     */
    public function runCommand(string $command, int $timeout = 120, array $env = []): ProcessResult
    {
        $inner = trim($command);

        if ($env !== [] && ! $this->needsSudo()) {
            $envPrefix = implode(' ', array_map(
                fn (string $key, string $value): string => $key.'='.escapeshellarg($value),
                array_keys($env),
                array_values($env),
            ));
            $inner = $envPrefix.' '.$inner;
        }

        if ($this->needsSudo() && preg_match('#^systemctl\s+(start|restart|is-active)\s+([^\s;&|]+)$#', $inner, $matches)) {
            $systemctl = is_executable('/usr/bin/systemctl') ? '/usr/bin/systemctl' : '/bin/systemctl';
            $argv = ['sudo', '-n', $systemctl, $matches[1], $matches[2]];

            return $this->executor->run('', ['timeout' => $timeout, 'argv' => $argv]);
        }

        $shell = 'bash -c '.escapeshellarg($inner);

        if ($this->needsSudo()) {
            $shell = 'sudo -n '.$shell;
        }

        return $this->executor->run($shell, ['timeout' => $timeout]);
    }

    private function needsSudo(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        if (! function_exists('posix_geteuid')) {
            return true;
        }

        return posix_geteuid() !== 0;
    }
}
