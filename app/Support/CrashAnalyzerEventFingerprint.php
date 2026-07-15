<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Empreinte stable pour dédupliquer événements OOM / kernel (sans PID éphémère).
 */
final class CrashAnalyzerEventFingerprint
{
    public static function from(string $eventType, string $details): string
    {
        $normalized = preg_replace('/\[[^\]]+\]\s*/', '', trim($details)) ?? trim($details);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if ($eventType === 'oom_killer' && preg_match('/Killed process\s+\d+\s+\(([^)]+)\)/i', $normalized, $matches)) {
            return $eventType.':proc='.strtolower($matches[1]);
        }

        if ($eventType === 'oom_killer' && str_contains(strtolower($normalized), 'out of memory')) {
            return $eventType.':generic';
        }

        return hash('sha256', $eventType.':'.$normalized);
    }
}
