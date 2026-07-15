<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Support\UserTimezone;
use Illuminate\Support\Carbon;

/**
 * Fenêtres temporelles pour métriques — bornes en UTC (BDD), libellés en fuseau utilisateur.
 */
final class MonitoringPeriodResolver
{
    /**
     * @return array{from: Carbon, to: Carbon, resolution: string, label: string, preset: string}
     */
    public function resolve(string $preset): array
    {
        $to = now();
        $preset = strtolower($preset);

        $base = match ($preset) {
            '1h' => [
                'from' => $to->copy()->subHour(),
                'resolution' => '1m',
                'label' => '1 heure',
            ],
            '6h' => [
                'from' => $to->copy()->subHours(6),
                'resolution' => '1m',
                'label' => '6 heures',
            ],
            '24h' => [
                'from' => $to->copy()->subDay(),
                'resolution' => '1m',
                'label' => '24 heures',
            ],
            '3d' => [
                'from' => $to->copy()->subDays(3),
                'resolution' => '5m',
                'label' => '3 jours',
            ],
            '7d' => [
                'from' => $to->copy()->subDays(7),
                'resolution' => '5m',
                'label' => '7 jours',
            ],
            '30d', '1m' => [
                'from' => $to->copy()->subDays(30),
                'resolution' => '1h',
                'label' => '30 jours',
            ],
            '3m' => [
                'from' => $to->copy()->subMonths(3),
                'resolution' => '1h',
                'label' => '3 mois',
            ],
            '6m' => [
                'from' => $to->copy()->subMonths(6),
                'resolution' => '1h',
                'label' => '6 mois',
            ],
            '1y' => [
                'from' => $to->copy()->subYear(),
                'resolution' => '1h',
                'label' => '1 an',
            ],
            default => [
                'from' => $to->copy()->subDay(),
                'resolution' => '1m',
                'label' => '24 heures',
            ],
        };

        return [
            'from' => $base['from'],
            'to' => $to,
            'resolution' => $base['resolution'],
            'label' => $base['label'],
            'preset' => $preset === 'default' ? '24h' : $preset,
        ];
    }

    public function timelineSlots(string $preset): int
    {
        return match (strtolower($preset)) {
            '1h' => 60,
            '6h' => 72,
            '24h' => 96,
            '3d' => 72,
            '7d' => 84,
            default => 72,
        };
    }

    /**
     * Repères temporels sous la barre Up/Down (positions en %).
     *
     * @return list<array{label: string, percent: float}>
     */
    public function timelineAxisLabels(Carbon $from, Carbon $to, string $preset): array
    {
        $presetKey = strtolower($preset);
        $ticks = match ($presetKey) {
            '1h' => 7,
            '6h' => 7,
            '24h' => 8,
            '3d' => 7,
            '7d' => 7,
            default => 6,
        };

        $duration = max(60, $to->timestamp - $from->timestamp);
        $labels = [];

        for ($i = 0; $i < $ticks; $i++) {
            $ratio = $ticks === 1 ? 0.0 : $i / ($ticks - 1);
            $at = $from->copy()->addSeconds((int) round($duration * $ratio));
            $format = match (true) {
                $duration <= 86400 => 'H:i',
                $duration <= 604800 => 'd/m H:i',
                default => 'd/m',
            };

            $labels[] = [
                'label' => UserTimezone::format($at, $format),
                'percent' => round($ratio * 100, 2),
            ];
        }

        return $labels;
    }
}
