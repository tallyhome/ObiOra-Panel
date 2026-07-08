<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Server;

final class SlaveInstallHelper
{
    public function installUrl(): string
    {
        return url('/install/slave-agent.sh');
    }

    public function remoteCommand(?Server $server): string
    {
        if ($server === null) {
            return '# Aucun serveur sélectionné';
        }

        $port = (int) ($server->primaryNode?->port ?? 9100);

        return sprintf(
            'curl -fsSL %s | sudo OBIORA_AGENT_TOKEN=%s OBIORA_AGENT_PORT=%d bash',
            $this->installUrl(),
            $server->agent_token,
            $port,
        );
    }
}
