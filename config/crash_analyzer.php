<?php

declare(strict_types=1);

return [

    'interval_seconds' => (int) env('OBIORA_CRASH_INTERVAL', 5),

    'history_minutes' => (int) env('OBIORA_CRASH_HISTORY_MINUTES', 60),

    'retention_hours' => (int) env('OBIORA_CRASH_RETENTION_HOURS', 72),

    'notifications' => [
        'email' => (bool) env('OBIORA_CRASH_NOTIFY_EMAIL', true),
        'discord' => (bool) env('OBIORA_CRASH_NOTIFY_DISCORD', false),
        'discord_webhook' => env('OBIORA_CRASH_DISCORD_WEBHOOK'),
        'telegram' => (bool) env('OBIORA_CRASH_NOTIFY_TELEGRAM', false),
        'telegram_bot_token' => env('OBIORA_CRASH_TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id' => env('OBIORA_CRASH_TELEGRAM_CHAT_ID'),
        'slack' => (bool) env('OBIORA_CRASH_NOTIFY_SLACK', false),
        'slack_webhook' => env('OBIORA_CRASH_SLACK_WEBHOOK'),
        'webhook' => (bool) env('OBIORA_CRASH_NOTIFY_WEBHOOK', false),
        'webhook_url' => env('OBIORA_CRASH_WEBHOOK_URL'),
        'push' => (bool) env('OBIORA_CRASH_NOTIFY_PUSH', true),
    ],

    'critical_event_types' => [
        'kernel_panic',
        'soft_lockup',
        'hard_lockup',
        'rcu_stall',
        'oom_killer',
        'watchdog',
        'nvme_error',
        'raid_error',
        'smart_error',
        'ecc_error',
        'filesystem_ro',
        'virtualizor_crash',
        'network_loss',
        'unexpected_reboot',
        'io_error',
        'segfault',
        'memory_pressure',
        'systemd_failed',
    ],

    /** Fenêtre de dédoublonnage des alertes panel (minutes). */
    'alert_dedupe_minutes' => (int) env('OBIORA_CRASH_ALERT_DEDUPE_MINUTES', 60),

    /** Résolution auto des alertes si aucun nouvel événement du même type (minutes). */
    'alert_auto_resolve_minutes' => (int) env('OBIORA_CRASH_ALERT_AUTO_RESOLVE_MINUTES', 60),

];
