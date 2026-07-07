<?php

declare(strict_types=1);

return [

    'name' => env('OBIORA_NAME', 'ObiOra Panel'),

    'version' => ltrim(trim((string) @file_get_contents(dirname(__DIR__).'/VERSION')), 'v') ?: '1.9.2',

    'installation_uuid' => env('OBIORA_INSTALLATION_UUID'),

    'github' => [
        'repository' => env('OBIORA_GITHUB_REPO', 'tallyhome/ObiOra-Panel'),
        'api_url' => env('OBIORA_GITHUB_API', 'https://api.github.com'),
    ],

    'paths' => [
        'install' => base_path('install'),
        'packages' => base_path('packages'),
        'plugins' => base_path('plugins'),
        'agent' => base_path('agent'),
        'backups' => storage_path('app/backups'),
        'updates' => storage_path('app/updates'),
    ],

    'supported_os' => [
        'debian' => ['11', '12'],
        'ubuntu' => ['20.04', '22.04', '24.04'],
        'almalinux' => ['8', '9', '10'],
        'rocky' => ['8', '9', '10'],
    ],

    'default_server' => [
        'name' => 'Local Server',
        'type' => 'local',
        'is_master' => true,
    ],

    'websites' => [
        'web_root' => env('OBIORA_WEB_ROOT', '/var/www'),
        'php_versions' => ['8.1', '8.2', '8.3', '8.4'],
        'default_php' => '8.3',
    ],

    'databases' => [
        'default_charset' => 'utf8mb4',
        'default_collation' => 'utf8mb4_unicode_ci',
        'default_host' => 'localhost',
        'reserved_names' => ['mysql', 'information_schema', 'performance_schema', 'sys', 'obiora_panel'],
    ],

    'docker' => [
        'allowed_actions' => ['start', 'stop', 'restart', 'remove'],
        'max_log_lines' => 500,
    ],

    'backups' => [
        'storage_root' => env('OBIORA_BACKUP_ROOT', '/var/backups/obiora'),
        'types' => ['database', 'files', 'full'],
    ],

    'diagnostics' => [
        'signing_key' => env('OBIORA_DOCTOR_SIGNING_KEY'),
        'require_signature' => (bool) env('OBIORA_DOCTOR_REQUIRE_SIGNATURE', false),
        'ping_interval_seconds' => (int) env('OBIORA_MONITOR_PING_INTERVAL', 30),
        'ping_history_hours' => (int) env('OBIORA_MONITOR_HISTORY_HOURS', 24),
        'alerts_email' => (bool) env('OBIORA_MONITOR_ALERTS_EMAIL', true),
    ],

    'monitoring' => [
        'stream_interval_seconds' => (int) env('OBIORA_MONITOR_STREAM_INTERVAL', 5),
    ],

    'realtime' => [
        'enabled' => (bool) env('OBIORA_REALTIME_ENABLED', false),
        'metrics_interval_seconds' => (int) env('OBIORA_REALTIME_METRICS_INTERVAL', 5),
        'fallback_poll_seconds' => (int) env('OBIORA_REALTIME_FALLBACK_POLL', 10),
    ],

];
