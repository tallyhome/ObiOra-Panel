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
            'sudo -n %s __obiora_env 3 OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%s OBIORA_AGENT_TOKEN=%s',
            escapeshellarg(base_path('agent/scripts/bootstrap-doctor-agent.sh')),
            base64_encode(rtrim((string) config('app.url'), '/')),
            base64_encode((string) $server->id),
            base64_encode((string) ($server->agent_token ?? '')),
        );
    }

    public function suiteUrl(): string
    {
        return url('/install/doctor-suite.sh');
    }

    public function crashAnalyzerUrl(): string
    {
        return url('/install/crash-analyzer.sh');
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

    public function remoteSuiteCommand(?Server $server): string
    {
        if ($server === null) {
            return '# Aucun serveur sélectionné';
        }

        return $this->suiteInstallShellCommand($server);
    }

    public function suiteInstallScriptPath(): string
    {
        return base_path('agent/scripts/doctor-suite-local.sh');
    }

    /**
     * Script distant (curl | sudo bash) — ne pas utiliser pour le serveur local du panel.
     */
    public function suiteRemoteScriptUrl(): string
    {
        return url('/install/doctor-suite.sh');
    }

    /**
     * @return array<string, string>
     */
    public function suiteInstallEnv(
        Server $server,
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        bool $installCrashHunter = true,
    ): array {
        return [
            'OBIORA_PANEL_URL' => rtrim((string) config('app.url'), '/'),
            'OBIORA_SERVER_ID' => (string) $server->id,
            'OBIORA_AGENT_TOKEN' => (string) ($server->agent_token ?? ''),
            'OBIORA_INSTALL_DOCTOR' => $installDoctor ? 'yes' : 'no',
            'OBIORA_INSTALL_CRASH_ANALYZER' => $installCrashAnalyzer ? 'yes' : 'no',
            'OBIORA_INSTALL_CRASH_HUNTER' => $installCrashHunter ? 'yes' : 'no',
            'OBIORA_SCRIPT_DIR' => base_path('agent/scripts'),
        ];
    }

    /**
     * Arguments pour exécution locale via sudo NOPASSWD (worker obiora-queue).
     *
     * @return list<string>
     */
    public function suiteInstallLocalArgs(
        Server $server,
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        bool $installCrashHunter = true,
    ): array {
        $env = $this->suiteInstallEnv($server, $installDoctor, $installCrashAnalyzer, $installCrashHunter);
        $args = ['__obiora_env', (string) count($env)];

        foreach ($env as $key => $value) {
            $args[] = $key.'='.base64_encode($value);
        }

        return $args;
    }

    public function suiteInstallShellCommand(
        Server $server,
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        bool $installCrashHunter = true,
    ): string {
        $panelUrl = rtrim((string) config('app.url'), '/');

        return sprintf(
            'curl -fsSL %s | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s OBIORA_INSTALL_DOCTOR=%s OBIORA_INSTALL_CRASH_ANALYZER=%s OBIORA_INSTALL_CRASH_HUNTER=%s bash',
            escapeshellarg($panelUrl.'/install/doctor-suite.sh'),
            escapeshellarg($panelUrl),
            $server->id,
            escapeshellarg((string) ($server->agent_token ?? '')),
            $installDoctor ? 'yes' : 'no',
            $installCrashAnalyzer ? 'yes' : 'no',
            $installCrashHunter ? 'yes' : 'no',
        );
    }

    /**
     * @return list<string>
     */
    public function suiteComponentList(
        bool $installDoctor = true,
        bool $installCrashAnalyzer = true,
        bool $installCrashHunter = true,
    ): array {
        return array_values(array_filter([
            $installDoctor ? 'doctor' : null,
            $installCrashAnalyzer ? 'crash_analyzer' : null,
            $installCrashHunter ? 'crash_hunter' : null,
        ]));
    }

    public function remoteCrashAnalyzerCommand(?Server $server): string
    {
        if ($server === null) {
            return '# Aucun serveur sélectionné';
        }

        return sprintf(
            'curl -fsSL %s | sudo OBIORA_PANEL_URL=%s OBIORA_SERVER_ID=%d OBIORA_AGENT_TOKEN=%s bash',
            $this->crashAnalyzerUrl(),
            rtrim((string) config('app.url'), '/'),
            $server->id,
            $server->agent_token,
        );
    }
}
