<?php

declare(strict_types=1);

namespace App\Support;

final class DiagnosticConfidence
{
    /**
     * @return array{percent: int, label: string, level: string}|null
     */
    public static function format(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;
        $percent = $float <= 1.0 ? (int) round($float * 100) : (int) round($float);
        $percent = max(0, min(100, $percent));

        return [
            'percent' => $percent,
            'label' => "{$percent} % de confiance",
            'level' => $percent >= 75 ? 'high' : ($percent >= 45 ? 'medium' : 'low'),
        ];
    }
}
