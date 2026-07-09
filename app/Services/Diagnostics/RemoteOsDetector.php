<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\DTOs\SshConnection;

final class RemoteOsDetector
{
    public function __construct(
        private readonly SshRemoteExecutor $ssh,
    ) {}

    /**
     * @return array{name: string, version: string|null}|null
     */
    public function detect(SshConnection $connection): ?array
    {
        $result = $this->ssh->run(
            $connection,
            'if [ -f /etc/os-release ]; then . /etc/os-release && echo "OBIORA_OS_NAME=$NAME" && echo "OBIORA_OS_VERSION=$VERSION_ID"; elif [ -f /etc/redhat-release ]; then echo "OBIORA_OS_NAME=$(cat /etc/redhat-release)"; else uname -s; fi',
        );

        if (! $result['success']) {
            return null;
        }

        $name = null;
        $version = null;

        foreach (explode("\n", $result['output']) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'OBIORA_OS_NAME=')) {
                $name = trim(substr($line, 15), '"');
            }
            if (str_starts_with($line, 'OBIORA_OS_VERSION=')) {
                $version = trim(substr($line, 18), '"');
            }
        }

        if ($name === null || $name === '') {
            $name = trim($result['output']) !== '' ? trim(explode("\n", $result['output'])[0]) : null;
        }

        if ($name === null || $name === '') {
            return null;
        }

        return ['name' => $name, 'version' => $version];
    }
}
