<?php

declare(strict_types=1);

return [

    'path' => env('OBIORA_PACKAGES_PATH', base_path('packages')),

    'categories' => [
        'monitoring' => 'Monitoring',
        'media' => 'Média',
        'download' => 'Téléchargement',
        'dev' => 'Développement',
    ],

];
