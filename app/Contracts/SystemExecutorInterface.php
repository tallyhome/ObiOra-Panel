<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ProcessResult;

interface SystemExecutorInterface
{
    public function run(string $command, array $options = []): ProcessResult;

    public function runScript(string $path, array $args = []): ProcessResult;

    public function runAsUser(string $user, string $command, array $options = []): ProcessResult;
}
