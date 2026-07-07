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
     */
    public function run(string $script, array $args = [], int $timeout = 120): ProcessResult
    {
        // Exécuter le script directement (pas via `bash`) pour correspondre aux
        // règles sudoers : NOPASSWD: .../agent/scripts/*.sh
        $command = escapeshellarg($script);

        if ($args !== []) {
            $command .= ' '.implode(' ', array_map('escapeshellarg', $args));
        }

        if ($this->needsSudo()) {
            $command = 'sudo -n '.$command;
        }

        return $this->executor->run($command, ['timeout' => $timeout]);
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
