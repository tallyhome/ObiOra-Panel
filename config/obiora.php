<?php

declare(strict_types=1);

return [

    'name' => env('OBIORA_NAME', 'ObiOra Panel'),

    'version' => '1.4.0',

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

];
