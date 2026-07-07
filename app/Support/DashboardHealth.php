<?php

declare(strict_types=1);

namespace App\Support;

final class DashboardHealth
{
    /**
     * @param  array<string, mixed>  $metrics
     * @return array{load: string, disk: string, memory: string, load_pct: float, disk_pct: float, memory_pct: float}
     */
    public static function glance(array $metrics): array
    {
        $cores = max(1, (int) ($metrics['cpu']['cores'] ?? 1));
        $load1 = (float) ($metrics['cpu']['load_1'] ?? 0);
        $loadPct = min(100, ($load1 / $cores) * 100);

        $diskPct = (float) ($metrics['disk']['percent'] ?? 0);
        $memPct = (float) ($metrics['memory']['percent'] ?? 0);

        return [
            'load' => self::statusFromPercent($loadPct, 70, 90),
            'disk' => self::statusFromPercent($diskPct, 75, 90),
            'memory' => self::statusFromPercent($memPct, 75, 90),
            'load_pct' => round($loadPct, 1),
            'disk_pct' => $diskPct,
            'memory_pct' => $memPct,
        ];
    }

    public static function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $pow), $precision).' '.$units[$pow];
    }

    /** Format français compact : 500 Mo, 2,5 Go */
    public static function formatBytesFr(int|float $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 o';
        }

        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = round($bytes / (1024 ** $pow), $precision);

        $formatted = $pow === 0
            ? (string) (int) $value
            : str_replace('.', ',', rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.'));

        return $formatted.' '.$units[$pow];
    }

    private static function statusFromPercent(float $pct, float $warn, float $danger): string
    {
        if ($pct >= $danger) {
            return 'danger';
        }

        if ($pct >= $warn) {
            return 'warning';
        }

        return 'ok';
    }
}
