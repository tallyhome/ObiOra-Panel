<?php

declare(strict_types=1);

namespace App\Services\CrashHunter;

use App\Models\Server;
use App\Services\System\PrivilegedScriptRunner;
use Illuminate\Support\Facades\Log;

/**
 * Audit et purge de l'espace disque CrashHunter (/opt/crashhunter/bundles…).
 */
final class CrashHunterDiskService
{
    public function __construct(
        private readonly PrivilegedScriptRunner $scripts,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     path: string,
     *     total_bytes: int,
     *     bundles_bytes: int,
     *     reports_bytes: int,
     *     logs_bytes: int,
     *     data_bytes: int,
     *     bundle_count: int,
     *     report_count: int,
     *     warning: bool,
     *     message: string
     * }
     */
    public function auditLocal(): array
    {
        $fallback = [
            'available' => is_dir('/opt/crashhunter'),
            'path' => '/opt/crashhunter',
            'total_bytes' => 0,
            'bundles_bytes' => 0,
            'reports_bytes' => 0,
            'logs_bytes' => 0,
            'data_bytes' => 0,
            'bundle_count' => 0,
            'report_count' => 0,
            'warning' => false,
            'message' => '',
        ];

        if (PHP_OS_FAMILY !== 'Linux' || ! is_dir('/opt/crashhunter')) {
            $fallback['message'] = 'CrashHunter non installé sur ce serveur panel.';

            return $fallback;
        }

        $script = base_path('agent/scripts/crashhunter-disk-purge.sh');
        if (! is_file($script)) {
            return $this->auditViaPhpFallback();
        }

        $result = $this->scripts->run($script, ['audit'], 60);
        $output = trim($result->output.$result->errorOutput);
        $json = $this->extractJson($output);

        if ($json === null) {
            Log::warning('CrashHunter disk audit failed', ['output' => $output]);

            return $this->auditViaPhpFallback();
        }

        return $this->normalizeAudit($json);
    }

    /**
     * @return array{success: bool, message: string, audit: array<string, mixed>}
     */
    public function purgeLocal(string $mode = 'keep', int $keep = 3): array
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! is_dir('/opt/crashhunter')) {
            return ['success' => false, 'message' => 'CrashHunter introuvable sur /opt/crashhunter.', 'audit' => $this->auditLocal()];
        }

        $script = base_path('agent/scripts/crashhunter-disk-purge.sh');
        if (! is_file($script)) {
            return ['success' => false, 'message' => 'Script crashhunter-disk-purge.sh manquant.', 'audit' => $this->auditLocal()];
        }

        $args = $mode === 'all' ? ['all'] : ['keep', (string) max(0, $keep)];
        $result = $this->scripts->run($script, $args, 300);
        $output = trim($result->output.$result->errorOutput);
        $success = $result->successful && str_contains($output, 'OK:');
        $audit = $this->extractJson($output);
        $normalized = $audit !== null ? $this->normalizeAudit($audit) : $this->auditLocal();

        if (! $success) {
            return [
                'success' => false,
                'message' => $output !== '' ? $output : 'Échec purge CrashHunter (sudoers / permissions).',
                'audit' => $normalized,
            ];
        }

        $freedLabel = $this->formatBytes($normalized['total_bytes']);

        return [
            'success' => true,
            'message' => $mode === 'all'
                ? "Bundles et rapports CrashHunter vidés. Espace restant : {$freedLabel}."
                : "Purge OK (gardé {$keep} derniers). CrashHunter utilise maintenant {$freedLabel}.",
            'audit' => $normalized,
        ];
    }

    /**
     * Uniquement pour serveur panel local / master.
     */
    public function isLocalHunterServer(Server $server): bool
    {
        return (bool) $server->is_master || $server->type->value === 'local';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function normalizeAudit(array $json): array
    {
        $total = (int) ($json['total_bytes'] ?? 0);
        $bundles = (int) ($json['bundles_bytes'] ?? 0);
        $warning = $total >= 5 * 1024 * 1024 * 1024 || ($json['bundle_count'] ?? 0) >= 20;

        return [
            'available' => true,
            'path' => (string) ($json['path'] ?? '/opt/crashhunter'),
            'total_bytes' => $total,
            'bundles_bytes' => $bundles,
            'reports_bytes' => (int) ($json['reports_bytes'] ?? 0),
            'logs_bytes' => (int) ($json['logs_bytes'] ?? 0),
            'data_bytes' => (int) ($json['data_bytes'] ?? 0),
            'bundle_count' => (int) ($json['bundle_count'] ?? 0),
            'report_count' => (int) ($json['report_count'] ?? 0),
            'warning' => $warning,
            'message' => $warning
                ? 'Espace CrashHunter élevé — purgez les bundles (souvent ~300 Mo chacun).'
                : '',
            'total_human' => $this->formatBytes($total),
            'bundles_human' => $this->formatBytes($bundles),
            'reports_human' => $this->formatBytes((int) ($json['reports_bytes'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditViaPhpFallback(): array
    {
        $base = '/opt/crashhunter';
        $paths = [
            'bundles' => $base.'/bundles',
            'reports' => $base.'/reports',
            'logs' => $base.'/logs',
            'data' => $base.'/data',
        ];
        $sizes = [];
        foreach ($paths as $key => $path) {
            $sizes[$key] = is_dir($path) ? $this->directorySize($path) : 0;
        }
        $total = is_dir($base) ? $this->directorySize($base) : 0;
        $bundleCount = is_dir($paths['bundles']) ? max(0, count(glob($paths['bundles'].'/*', GLOB_ONLYDIR) ?: []) ) : 0;
        $reportCount = is_dir($paths['reports']) ? max(0, count(glob($paths['reports'].'/*', GLOB_ONLYDIR) ?: []) ) : 0;

        return $this->normalizeAudit([
            'path' => $base,
            'total_bytes' => $total,
            'bundles_bytes' => $sizes['bundles'],
            'reports_bytes' => $sizes['reports'],
            'logs_bytes' => $sizes['logs'],
            'data_bytes' => $sizes['data'],
            'bundle_count' => $bundleCount,
            'report_count' => $reportCount,
        ]);
    }

    private function directorySize(string $path): int
    {
        $bytes = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return $bytes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $output): ?array
    {
        foreach (array_reverse(preg_split('/\r\n|\r|\n/', $output) ?: []) as $line) {
            $line = trim($line);
            if ($line !== '' && str_starts_with($line, '{')) {
                try {
                    /** @var array<string, mixed> $decoded */
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                    return $decoded;
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1).' Go';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 0).' Mo';
        }

        return number_format(max(0, $bytes) / 1024, 0).' Ko';
    }
}
