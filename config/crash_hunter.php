<?php

declare(strict_types=1);

return [
    'history_minutes' => (int) env('CRASH_HUNTER_HISTORY_MINUTES', 60),
    'snapshot_retention_hours' => (int) env('CRASH_HUNTER_SNAPSHOT_RETENTION_HOURS', 24),
    'metrics_retention_hours' => (int) env('CRASH_HUNTER_METRICS_RETENTION_HOURS', 72),
    'witness_timeout_seconds' => (int) env('CRASH_HUNTER_WITNESS_TIMEOUT', 60),
    'witness_death_seconds' => (int) env('CRASH_HUNTER_WITNESS_DEATH', 90),
    'witness_stale_seconds' => (int) env('CRASH_HUNTER_WITNESS_STALE', 90),
    'push_interval_seconds' => (int) env('CRASH_HUNTER_PUSH_INTERVAL', 30),

    /** unknown | same_host | same_provider | independent */
    'witness_independence' => env('CRASH_HUNTER_WITNESS_INDEPENDENCE', 'unknown'),

    'managed_services' => [
        'crashhunter',
        'obiora-crash-analyzer',
        'obiora-doctor-agent',
        'obiora-doctor-agent.timer',
    ],
];
