<?php

declare(strict_types=1);

return [

    'path' => env('OBIORA_PACKAGES_PATH', base_path('packages')),

    'categories' => [
        'monitoring' => 'Monitoring',
        'media' => 'Média',
        'download' => 'Téléchargement',
        'cloud' => 'Cloud & fichiers',
        'system' => 'Système',
        'network' => 'Réseau',
        'tools' => 'Outils',
        'dev' => 'Développement',
    ],

    /**
     * Alias slug ObiOra → icône dashboard-icons (Homarr).
     *
     * @var array<string, string>
     */
    'icon_aliases' => [
        'btsync' => 'resilio',
        'calibrecs' => 'calibre',
        'calibreweb' => 'calibre-web',
        'jfago' => 'jellyfin',
        'plexpy' => 'tautulli',
        'sonarrold' => 'sonarr',
        'xmr-stak' => 'monero',
        'xmr-stak-cpu' => 'monero',
        'qui' => 'quassel',
        'rapidleech' => 'jdownloader',
        'nzbhydra' => 'nzbhydra2',
        'sickgear' => 'sickbeard',
        'netronome' => 'netdata',
        'mango' => 'mangaplus',
    ],

    /**
     * Applications nécessitant une base MySQL/MariaDB externe (création auto à l'install).
     *
     * @var array<string, array{name_prefix?: string}>
     */
    'database_provision' => [
        'nextcloud' => ['name_prefix' => 'nextcloud'],
    ],

];
