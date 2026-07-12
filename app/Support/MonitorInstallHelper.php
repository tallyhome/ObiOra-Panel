<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Server;

final class MonitorInstallHelper
{
    public function remoteCommand(Server $server): string
    {
        $panelUrl = rtrim((string) config('app.url'), '/');

        return sprintf(
            'curl -fsSL %s/install/monitor-agent.sh | sudo bash -s -- --panel-url=%s --server-id=%d --agent-token=%s',
            $panelUrl,
            escapeshellarg($panelUrl),
            $server->id,
            escapeshellarg($server->agent_token),
        );
    }

    public function installCommand(Server $server): string
    {
        return $this->remoteCommand($server);
    }

    public function uninstallCommand(): string
    {
        return 'sudo bash /opt/obiora-monitor/bin/obiora-metrics-uninstall.sh 2>/dev/null || sudo bash /opt/obiora-panel/agent/scripts/obiora-monitor-uninstall.sh';
    }

    public function slaveInstallCommand(Server $server): string
    {
        $panelUrl = rtrim((string) config('app.url'), '/');

        return sprintf(
            'curl -fsSL %s/install/slave-agent.sh | sudo OBIORA_AGENT_TOKEN=%s bash',
            $panelUrl,
            escapeshellarg($server->agent_token),
        );
    }
}
