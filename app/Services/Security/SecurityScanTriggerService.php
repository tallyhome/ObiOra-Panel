<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;

/**
 * Déclenche un scan sécurité Doctor sur le serveur panel ou distant.
 */
final class SecurityScanTriggerService
{
    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
        private readonly ServerManager $servers,
    ) {}

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function trigger(Server $server): array
    {
        if ($server->is_master || $server->type->value === 'local') {
            return $this->triggerLocal($server);
        }

        return $this->triggerRemote($server);
    }

    /**
     * @return array{success: bool, message: string, output: string}
     */
    private function triggerLocal(Server $server): array
    {
        $scanScript = base_path('agent/scripts/run-security-scan.sh');

        if (! is_file($scanScript)) {
            $result = $this->scripts->run(
                'systemctl start obiora-doctor-agent.service',
                [],
                180,
            );
            $output = trim($result->output.$result->errorOutput);

            return [
                'success' => $result->successful,
                'message' => $result->successful ? 'Scan Doctor lancé.' : 'Échec lancement scan.',
                'output' => $output,
            ];
        }

        $env = [
            'OBIORA_PANEL_URL' => rtrim((string) config('app.url'), '/'),
            'OBIORA_SERVER_ID' => (string) $server->id,
            'OBIORA_AGENT_TOKEN' => (string) ($server->agent_token ?? ''),
        ];

        $envPrefix = implode(' ', array_map(
            fn (string $k, string $v) => $k.'='.escapeshellarg($v),
            array_keys($env),
            array_values($env),
        ));

        $result = $this->scripts->run('bash -c '.escapeshellarg("{$envPrefix} {$scanScript}"), [], 300);
        $output = trim($result->output.$result->errorOutput);
        $success = $result->successful && str_contains($output, 'OK:');

        return [
            'success' => $success,
            'message' => $success ? 'Scan sécurité terminé et envoyé.' : ($output ?: 'Échec scan sécurité.'),
            'output' => $output,
        ];
    }

    /**
     * @return array{success: bool, message: string, output: string}
     */
    private function triggerRemote(Server $server): array
    {
        $executor = $this->servers->executorFor($server);
        $result = $executor->run(
            'systemctl start obiora-doctor-agent.service 2>/dev/null || /opt/obiora-doctor-agent/run-security-scan.sh 2>/dev/null || /opt/obiora-doctor-agent/run-scan.sh',
            ['timeout' => 300],
        );
        $output = trim($result->output.$result->errorOutput);
        $success = $result->successful || str_contains($output, 'OK:');

        return [
            'success' => $success,
            'message' => $success ? 'Scan sécurité lancé sur le serveur distant.' : ($output ?: 'Échec lancement scan distant.'),
            'output' => $output,
        ];
    }
}
