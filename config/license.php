<?php

declare(strict_types=1);

return [

    'enabled' => env('OBIORA_LICENSE_ENABLED', false),

    'admin_licence_url' => env('OBIORA_ADMIN_LICENCE_URL'),

    'grace_period_days' => (int) env('OBIORA_LICENSE_GRACE_DAYS', 7),

    'plans' => [
        'free' => [
            'max_servers' => 1,
            'max_users' => 3,
            'modules' => ['dashboard', 'servers', 'services', 'monitoring'],
        ],
        'pro' => [
            'max_servers' => 10,
            'max_users' => 25,
            'modules' => '*',
        ],
        'enterprise' => [
            'max_servers' => -1,
            'max_users' => -1,
            'modules' => '*',
        ],
    ],

];
