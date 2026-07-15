<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\SystemExecutorInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fichiers et contrats indispensables au pipeline de mise à jour du panel.
 * Toute release doit conserver ces chemins intacts (voir tests + CI maj-integrity).
 */
final class PanelUpdateIntegrity
{
    /** @var list<string> Chemins relatifs à la racine du panel */
    public const CRITICAL_RELATIVE_PATHS = [
        'install/update-panel.sh',
        'install/lib/update-recover.sh',
        'install/lib/panel-update-helper.sh',
        'install/lib/panel-update-helper.c',
        'install/lib/sudoers.sh',
        'install/lib/common.sh',
        'app/Services/Core/PanelUpdater.php',
        'app/Jobs/ApplyPanelUpdateJob.php',
        'app/Console/Commands/UpdateProgressCommand.php',
        'app/Console/Commands/CompleteUpdateCommand.php',
        'app/Console/Commands/RecoverPanelHttpCommand.php',
        'app/Console/Commands/PostDeployCommand.php',
        'VERSION',
    ];

    /** @var list<string> Scripts shell devant être exécutables */
    public const EXECUTABLE_SCRIPTS = [
        'install/update-panel.sh',
        'install/lib/update-recover.sh',
        'install/lib/panel-update-helper.sh',
        'agent/scripts/monitor-agent-install.sh',
        'agent/scripts/obiora-monitor-uninstall.sh',
    ];

    /**
     * @return array{ok: bool, missing: list<string>, warnings: list<string>}
     */
    public function verify(string $panelRoot): array
    {
        $missing = [];
        $warnings = [];

        foreach (self::CRITICAL_RELATIVE_PATHS as $relative) {
            $path = $panelRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_file($path)) {
                $missing[] = $relative;
            }
        }

        foreach (self::EXECUTABLE_SCRIPTS as $relative) {
            if (PHP_OS_FAMILY !== 'Linux') {
                continue;
            }

            $path = $panelRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (is_file($path) && ! is_executable($path)) {
                $warnings[] = "{$relative} n'est pas exécutable (+x)";
            }
        }

        if (! is_dir($panelRoot.DIRECTORY_SEPARATOR.'.git')) {
            $warnings[] = 'Dépôt git absent — MAJ depuis le panel indisponible';
        }

        return [
            'ok' => $missing === [],
            'missing' => $missing,
            'warnings' => $warnings,
        ];
    }

    public function restoreFromGit(string $panelRoot, SystemExecutorInterface $executor): void
    {
        if (! is_dir($panelRoot.'/.git')) {
            return;
        }

        $paths = array_values(array_filter(
            self::CRITICAL_RELATIVE_PATHS,
            static fn (string $path): bool => str_starts_with($path, 'install/'),
        ));

        if ($paths === []) {
            return;
        }

        $quoted = implode(' ', array_map(static fn (string $p): string => escapeshellarg($p), $paths));

        try {
            $executor->run(
                'git -C '.escapeshellarg($panelRoot).' checkout HEAD -- '.$quoted.' 2>&1',
                ['timeout' => 120],
            );
        } catch (Throwable $exception) {
            Log::warning('Restauration git des fichiers MAJ critiques échouée', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function ensureExecutableScripts(string $panelRoot): void
    {
        foreach (self::EXECUTABLE_SCRIPTS as $relative) {
            $path = $panelRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (is_file($path) && ! is_executable($path)) {
                @chmod($path, 0755);
            }
        }

        AgentScripts::ensureExecutable($panelRoot);
    }
}
