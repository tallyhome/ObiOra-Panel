<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\Server;

/**
 * Compare les versions des agents embarqués dans le panel vs celles reportées par les serveurs distants.
 */
final class DiagnosticsAgentVersionService
{
    /**
     * @return array<string, string|null>
     */
    public function bundledVersions(): array
    {
        return [
            'panel' => (string) config('obiora.version'),
            'crash_hunter' => $this->readCrashHunterVersion(),
            'crash_analyzer' => $this->readCrashAnalyzerVersion(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function remoteVersions(Server $server): array
    {
        $meta = $server->metadata ?? [];

        return [
            'crash_hunter' => isset($meta['crash_hunter']['version'])
                ? (string) $meta['crash_hunter']['version']
                : null,
            'crash_analyzer' => isset($meta['crash_analyzer']['version'])
                ? (string) $meta['crash_analyzer']['version']
                : null,
            'doctor' => $server->latestDiagnosticReport?->doctor_version,
        ];
    }

    /**
     * @return list<array{component: string, label: string, bundled: string|null, remote: string|null, outdated: bool}>
     */
    public function compare(Server $server): array
    {
        $bundled = $this->bundledVersions();
        $remote = $this->remoteVersions($server);

        $rows = [];
        foreach ([
            ['key' => 'crash_hunter', 'label' => 'CrashHunter'],
            ['key' => 'crash_analyzer', 'label' => 'Crash Analyzer'],
        ] as $component) {
            $key = $component['key'];
            $bundledVer = $bundled[$key] ?? null;
            $remoteVer = $remote[$key] ?? null;
            $rows[] = [
                'component' => $key,
                'label' => $component['label'],
                'bundled' => $bundledVer,
                'remote' => $remoteVer,
                'outdated' => $this->isOutdated($bundledVer, $remoteVer),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function outdatedComponents(Server $server): array
    {
        return collect($this->compare($server))
            ->filter(fn (array $row) => $row['outdated'])
            ->pluck('component')
            ->values()
            ->all();
    }

    public function needsUpgrade(Server $server): bool
    {
        return $this->outdatedComponents($server) !== [];
    }

    /**
     * Enregistre les versions panel comme versions distantes après install/MAJ réussie.
     *
     * @param  list<string>  $components  crash_hunter, crash_analyzer, doctor
     */
    public function stampDeployedVersions(Server $server, array $components): void
    {
        $bundled = $this->bundledVersions();
        $meta = $server->metadata ?? [];
        $now = now()->toIso8601String();
        $changed = false;

        foreach ($components as $component) {
            if (! in_array($component, ['crash_hunter', 'crash_analyzer'], true)) {
                continue;
            }

            $version = $bundled[$component] ?? null;
            if ($version === null || $version === '') {
                continue;
            }

            $meta[$component] = array_merge($meta[$component] ?? [], [
                'version' => $version,
                'last_upgrade_at' => $now,
            ]);
            $changed = true;
        }

        if ($changed) {
            $server->forceFill(['metadata' => $meta])->save();
        }
    }

    private function isOutdated(?string $bundled, ?string $remote): bool
    {
        if ($bundled === null || $bundled === '') {
            return false;
        }

        if ($remote === null || $remote === '') {
            return true;
        }

        return version_compare($remote, $bundled, '<');
    }

    private function readCrashHunterVersion(): ?string
    {
        $init = base_path('ObiOra-Suite/crashhunter/crashhunter/__init__.py');
        if (! is_readable($init)) {
            return null;
        }

        $content = file_get_contents($init);
        if ($content !== false && preg_match('/__version__\s*=\s*["\']([^"\']+)["\']/', $content, $m)) {
            return $m[1];
        }

        return null;
    }

    private function readCrashAnalyzerVersion(): ?string
    {
        $init = base_path('agent/crash-analyzer/crash_analyzer/__init__.py');
        if (! is_readable($init)) {
            return null;
        }

        $content = file_get_contents($init);
        if ($content !== false && preg_match('/__version__\s*=\s*["\']([^"\']+)["\']/', $content, $m)) {
            return $m[1];
        }

        return null;
    }
}
