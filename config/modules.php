<?php

declare(strict_types=1);

return [

    'path' => base_path('Modules'),

    'namespace' => 'Modules',

    'cache' => [
        'enabled' => env('MODULE_CACHE_ENABLED', true),
        'key' => 'obiora.modules',
        'lifetime' => 3600,
    ],

];
