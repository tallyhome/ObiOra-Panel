<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Server;

final class DoctorInstallHelper
{
    public function bootstrapUrl(): string
    {
        return url('/install/doctor-agent.sh');
    }

    public function localCommand(?Server $server): string
    {
        if ($server === null) {
            return '# Aucun serveur sélectionné';
        }

        return sprintf(
            'sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash %s/agent/scripts/bootstrap-doctor-agent.sh',
            rtrim((string) config('app.url'), '/'),
            $server->id,
            $server->agent_token,
            base_path(),
        );
    }

    public function remoteCommand(?Server $server): string
    {
        if ($server === null) {
            return '# Aucun serveur sélectionné';
        }

        return sprintf(
            'curl -fsSL %s | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
            $this->bootstrapUrl(),
            rtrim((string) config('app.url'), '/'),
            $server->id,
            $server->agent_token,
        );
    }
}
