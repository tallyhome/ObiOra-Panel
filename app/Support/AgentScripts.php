<?php

declare(strict_types=1);

namespace App\Support;

final class AgentScripts
{
    /** @var list<string> Scripts monitor livrés en Phase 1 (chmod après git pull / MAJ) */
    public const MONITOR_RELATIVE_PATHS = [
        'agent/scripts/monitor-agent-install.sh',
        'agent/scripts/obiora-monitor-uninstall.sh',
        'agent/monitor/obiora-metrics-push.sh',
        'agent/monitor/obiora-metrics-install.sh',
        'agent/monitor/obiora-metrics-uninstall.sh',
    ];

    /**
     * Réapplique le bit exécutable sur les scripts agent (git checkout les remet souvent en 644).
     */
    public static function ensureExecutable(?string $panelRoot = null): void
    {
        if (PHP_OS_FAMILY !== 'Linux' && PHP_OS_FAMILY !== 'Darwin') {
            return;
        }

        $root = rtrim($panelRoot ?? base_path(), DIRECTORY_SEPARATOR);

        $paths = array_merge(
            self::MONITOR_RELATIVE_PATHS,
            ['agent/bin/obiOra-agent'],
        );

        foreach ($paths as $relative) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (is_file($path)) {
                @chmod($path, 0755);
            }
        }

        $scriptsDir = $root.DIRECTORY_SEPARATOR.'agent'.DIRECTORY_SEPARATOR.'scripts';

        if (! is_dir($scriptsDir)) {
            return;
        }

        foreach (glob($scriptsDir.DIRECTORY_SEPARATOR.'*.sh') ?: [] as $script) {
            if (is_file($script)) {
                @chmod($script, 0755);
            }
        }
    }
}
