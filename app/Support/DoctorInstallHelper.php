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
