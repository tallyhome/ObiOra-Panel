<?php

declare(strict_types=1);

return [

    'retention_days' => (int) env('OBIORA_MONITOR_RETENTION_DAYS', 60),

    'check_retention_days' => (int) env('OBIORA_MONITOR_CHECK_RETENTION_DAYS', 60),

    /** Nombre de lignes affichées dans « Derniers checks » (moniteur site web). */
    'recent_checks_display_limit' => (int) env('OBIORA_MONITOR_RECENT_CHECKS_LIMIT', 200),

    'sample_retention_days' => (int) env('OBIORA_MONITOR_SAMPLE_RETENTION_DAYS', 60),

    'max_monitors' => env('OBIORA_MONITOR_MAX_MONITORS'),

    'max_servers' => env('OBIORA_MONITOR_MAX_SERVERS'),

    'status_page' => [
        'rate_limit_per_minute' => (int) env('OBIORA_STATUS_PAGE_RATE_LIMIT', 120),
    ],

    'prometheus' => [
        'enabled' => (bool) env('OBIORA_PROMETHEUS_ENABLED', false),
        'token' => env('OBIORA_PROMETHEUS_TOKEN'),
    ],

];
