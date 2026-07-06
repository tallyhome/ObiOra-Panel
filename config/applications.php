<?php

declare(strict_types=1);

return [

    'path' => base_path('packages'),

    'manifest' => 'manifest.json',

    'scripts' => [
        'install' => 'install.sh',
        'uninstall' => 'uninstall.sh',
        'update' => 'update.sh',
    ],

];
