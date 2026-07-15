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
        private readonly SecurityScanProgressService $progress,
    ) {}

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function trigger(Server $server): array
    {
        $this->progress->start($server->id);
        $this->progress->update($server->id, 15, 'Connexion au serveur et vérification de l\'agent Doctor…');

        $result = $server->is_master || $server->type->value === 'local'
            ? $this->triggerLocal($server)
            : $this->triggerRemote($server);

        $this->progress->finish($server->id, $result);

        return $result;
    }

    /**
     * @return array{success: bool, message: string, output: string}
     */
    private function triggerLocal(Server $server): array
    {
        $scanScript = base_path('agent/scripts/run-security-scan.sh');

        if (! is_file($scanScript)) {
            $this->progress->update($server->id, 40, 'Lancement du service obiora-doctor-agent…');
            $result = $this->scripts->runCommand('systemctl start obiora-doctor-agent.service', 180);
            $output = trim($result->output.$result->errorOutput);

            return [
                'success' => $result->successful,
                'message' => $result->successful ? 'Scan Doctor lancé.' : 'Échec lancement scan.',
                'output' => $output,
            ];
        }

        $this->progress->update($server->id, 35, 'Exécution du script run-security-scan.sh (modules SSH, firewall, rootkits…)');

        // Variables lues depuis agent.env / monitor-agent.env (sudoers = chemin script seul).
        $result = $this->scripts->run($scanScript, [], 300);
        $this->progress->update($server->id, 85, 'Analyse des résultats et envoi du rapport Doctor…');
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
        $this->progress->update($server->id, 40, 'Scan sécurité distant via SSH…');
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
