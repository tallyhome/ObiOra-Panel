<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

final class PanelStorageAudit
{
    /**
     * @return list<array{key: string, label: string, path: string, bytes: int, deletable: bool, hint: string}>
     */
    public function paths(): array
    {
        $base = base_path();

        return [
            $this->entry('logs', 'Logs Laravel', storage_path('logs'), true, 'Fichiers *.log — safe de vider les anciens'),
            $this->entry('views', 'Vues Blade compilées', storage_path('framework/views'), true, 'Régénérées automatiquement (view:clear)'),
            $this->entry('cache', 'Cache framework', storage_path('framework/cache/data'), true, 'Régénéré par optimize:clear'),
            $this->entry('sessions', 'Sessions fichier', storage_path('framework/sessions'), true, 'Si driver session=file'),
            $this->entry('crash_pdf', 'PDF Crash Analyzer', storage_path('app/crash-analyzer'), true, 'Rapports PDF ingérés'),
            $this->entry('crash_local', 'Fichiers Crash Analyzer', storage_path('app/private/crash-analyzer'), true, 'Exports locaux'),
            $this->entry('backups', 'Sauvegardes panel', storage_path('app/backups'), true, 'Vérifier avant suppression'),
            $this->entry('node_modules', 'node_modules (dev)', $base.'/node_modules', true, 'Peut être réinstallé via npm ci'),
            $this->entry('vendor', 'vendor PHP', $base.'/vendor', false, 'Ne pas supprimer — composer install'),
            $this->entry('public_build', 'Assets Vite compilés', public_path('build'), false, 'Rebuild via npm run build'),
        ];
    }

    /**
     * @return array{paths: list<array<string, mixed>>, database_bytes: int, total_bytes: int}
     */
    public function audit(): array
    {
        $paths = array_map(function (array $row): array {
            $row['bytes'] = $this->directorySize($row['path']);

            return $row;
        }, $this->paths());

        $databaseBytes = $this->databaseSizeBytes();
        $totalBytes = $databaseBytes + array_sum(array_column($paths, 'bytes'));

        return [
            'paths' => $paths,
            'database_bytes' => $databaseBytes,
            'total_bytes' => $totalBytes,
        ];
    }

    public function clearCompiledViews(): int
    {
        $dir = storage_path('framework/views');

        return $this->deleteFilesIn($dir);
    }

    public function clearFrameworkCache(): int
    {
        return $this->deleteFilesIn(storage_path('framework/cache/data'));
    }

    public function clearOldLogs(int $keepDays = 7): int
    {
        $deleted = 0;
        $cutoff = now()->subDays(max(1, $keepDays))->timestamp;
        $dir = storage_path('logs');

        if (! is_dir($dir)) {
            return 0;
        }

        foreach (glob($dir.'/*.log*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function clearCrashAnalyzerExports(): int
    {
        $deleted = 0;
        foreach ([storage_path('app/crash-analyzer'), storage_path('app/private/crash-analyzer')] as $dir) {
            $deleted += $this->deleteFilesIn($dir);
        }

        return $deleted;
    }

    private function databaseSizeBytes(): int
    {
        try {
            $db = (string) config('database.connections.mysql.database', '');
            if ($db === '') {
                return 0;
            }

            $row = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = ?',
                [$db],
            );

            return (int) ($row->size ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{key: string, label: string, path: string, bytes: int, deletable: bool, hint: string}
     */
    private function entry(string $key, string $label, string $path, bool $deletable, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'path' => $path,
            'bytes' => 0,
            'deletable' => $deletable,
            'hint' => $hint,
        ];
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return is_file($path) ? (int) filesize($path) : 0;
        }

        $bytes = 0;

        try {
            $finder = (new Finder)->in($path)->files();
            foreach ($finder as $file) {
                $bytes += $file->getSize();
            }
        } catch (\Throwable) {
            return 0;
        }

        return $bytes;
    }

    private function deleteFilesIn(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        foreach (File::allFiles($dir) as $file) {
            if (@unlink($file->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
