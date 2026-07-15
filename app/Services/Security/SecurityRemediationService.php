<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Server;
use App\Services\Core\ServerManager;
use App\Services\System\PrivilegedScriptRunner;

/**
 * Applique le durcissement sécurité sur le serveur panel ou via agent distant.
 * Aucune action modifiant l'accès SSH root ou mot de passe.
 */
final class SecurityRemediationService
{
    /** @var list<string> */
    private const ALLOWED_ACTIONS = [
        'enable-fail2ban',
        'secure-env-perms',
        'enable-firewall',
        'install-rkhunter',
        'all',
    ];

    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
        private readonly ServerManager $servers,
        private readonly SecurityScanTriggerService $scanTrigger,
    ) {}

    /**
     * @return array{success: bool, message: string, output: string}
     */
    public function apply(Server $server, string $action): array
    {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            return ['success' => false, 'message' => 'Action de durcissement inconnue.', 'output' => ''];
        }

        $script = base_path('agent/scripts/security-harden.sh');
        $isLocal = $server->is_master || $server->type->value === 'local';

        if ($isLocal) {
            $result = $this->scripts->run($script, [$action], 300);
            $output = trim($result->output.$result->errorOutput);
            $success = $result->successful && str_starts_with($output, 'OK:');

            if ($success) {
                $this->scanTrigger->trigger($server);
            }

            return [
                'success' => $success,
                'message' => $success ? 'Durcissement appliqué.' : ($output ?: 'Échec durcissement.'),
                'output' => $output,
            ];
        }

        $executor = $this->servers->executorFor($server);
        $remoteScript = '/opt/obiora-doctor-agent/security-harden.sh';
        $result = $executor->run("bash {$remoteScript} ".escapeshellarg($action), ['timeout' => 300]);
        $output = trim($result->output.$result->errorOutput);
        $success = $result->successful && str_contains($output, 'OK:');

        if ($success) {
            $this->scanTrigger->trigger($server);
        }

        return [
            'success' => $success,
            'message' => $success ? 'Durcissement appliqué sur le serveur distant.' : ($output ?: 'Échec durcissement distant.'),
            'output' => $output,
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function availableActions(): array
    {
        return [
            ['id' => 'enable-fail2ban', 'label' => 'Activer Fail2ban', 'description' => 'Installation et démarrage fail2ban'],
            ['id' => 'enable-firewall', 'label' => 'Activer pare-feu', 'description' => 'UFW ou firewalld (SSH, 80, 443)'],
            ['id' => 'secure-env-perms', 'label' => 'Sécuriser permissions fichiers', 'description' => 'chmod 600 sur .env, tokens agent'],
            ['id' => 'install-rkhunter', 'label' => 'Installer rkhunter', 'description' => 'Scanner rootkit + mise à jour signatures'],
            ['id' => 'all', 'label' => 'Durcissement sûr', 'description' => 'Fail2ban + pare-feu + permissions + rkhunter'],
        ];
    }
}
