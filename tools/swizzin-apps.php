<?php

declare(strict_types=1);

/**
 * Catalogue inspiré de Swizzin (https://github.com/swizzin/swizzin).
 * Scripts d'installation ObiOra — réécrits (Docker/APT), sans copie du code GPL.
 *
 * @return list<array<string, mixed>>
 */
return [
    // --- Média ---
    ['slug' => 'airsonic', 'name' => 'Airsonic', 'category' => 'media', 'description' => 'Serveur de streaming musical (fork Subsonic).', 'type' => 'docker', 'image' => 'airsonicadvanced/airsonic-advanced', 'port' => 4040],
    ['slug' => 'bazarr', 'name' => 'Bazarr', 'category' => 'media', 'description' => 'Gestion des sous-titres pour Sonarr/Radarr.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/bazarr:latest', 'port' => 6767],
    ['slug' => 'emby', 'name' => 'Emby', 'category' => 'media', 'description' => 'Serveur média avec transcodage.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/emby:latest', 'port' => 8096],
    ['slug' => 'jellyfin', 'name' => 'Jellyfin', 'category' => 'media', 'description' => 'Serveur média open source.', 'type' => 'native'],
    ['slug' => 'jfago', 'name' => 'Jfa-Go', 'category' => 'media', 'description' => 'Gestion des utilisateurs Jellyfin.', 'type' => 'docker', 'image' => 'hrfee/jfa-go', 'port' => 8056],
    ['slug' => 'lidarr', 'name' => 'Lidarr', 'category' => 'media', 'description' => 'Gestion et téléchargement de musique.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/lidarr:latest', 'port' => 8686],
    ['slug' => 'lounge', 'name' => 'Subsonic Lounge', 'category' => 'media', 'description' => 'Interface web pour Subsonic/Airsonic.', 'type' => 'docker', 'image' => 'opensubsonic/subsonic-api-proxy', 'port' => 4041],
    ['slug' => 'medusa', 'name' => 'Medusa', 'category' => 'media', 'description' => 'Gestionnaire de séries TV automatique.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/medusa:latest', 'port' => 8081],
    ['slug' => 'mylar', 'name' => 'Mylar3', 'category' => 'media', 'description' => 'Gestionnaire de bandes dessinées.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/mylar3:latest', 'port' => 8090],
    ['slug' => 'navidrome', 'name' => 'Navidrome', 'category' => 'media', 'description' => 'Serveur musical léger compatible Subsonic.', 'type' => 'docker', 'image' => 'deluan/navidrome:latest', 'port' => 4533],
    ['slug' => 'ombi', 'name' => 'Ombi', 'category' => 'media', 'description' => 'Demandes de médias pour Plex/Emby/Jellyfin.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/ombi:latest', 'port' => 3579],
    ['slug' => 'plex', 'name' => 'Plex', 'category' => 'media', 'description' => 'Serveur média Plex.', 'type' => 'native'],
    ['slug' => 'plexpy', 'name' => 'Tautulli (PlexPy)', 'category' => 'media', 'description' => 'Statistiques et monitoring Plex (ancien PlexPy).', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/tautulli:latest', 'port' => 8181],
    ['slug' => 'radarr', 'name' => 'Radarr', 'category' => 'media', 'description' => 'Gestion et téléchargement de films.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/radarr:latest', 'port' => 7878],
    ['slug' => 'readarr', 'name' => 'Readarr', 'category' => 'media', 'description' => 'Gestion et téléchargement de livres/ebooks.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/readarr:develop', 'port' => 8787],
    ['slug' => 'sickchill', 'name' => 'SickChill', 'category' => 'media', 'description' => 'Gestionnaire de séries TV (fork SickBeard).', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/sickchill:latest', 'port' => 8081],
    ['slug' => 'sickgear', 'name' => 'SickGear', 'category' => 'media', 'description' => 'Gestionnaire de séries TV.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/sickgear:latest', 'port' => 8081],
    ['slug' => 'sonarr', 'name' => 'Sonarr', 'category' => 'media', 'description' => 'Gestion et téléchargement de séries TV.', 'type' => 'native'],
    ['slug' => 'sonarrold', 'name' => 'Sonarr (v2)', 'category' => 'media', 'description' => 'Ancienne version Sonarr v2 (legacy).', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/sonarr:version-2.0.0.5161-ls3', 'port' => 8989],
    ['slug' => 'subsonic', 'name' => 'Subsonic', 'category' => 'media', 'description' => 'Serveur de streaming musical.', 'type' => 'docker', 'image' => 'linuxserver/subsonic', 'port' => 4040],
    ['slug' => 'tautulli', 'name' => 'Tautulli', 'category' => 'media', 'description' => 'Monitoring et statistiques Plex.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/tautulli:latest', 'port' => 8181],

    // --- Téléchargement ---
    ['slug' => 'autobrr', 'name' => 'Autobrr', 'category' => 'download', 'description' => 'Automatisation IRC/RSS pour torrents.', 'type' => 'docker', 'image' => 'ghcr.io/autobrr/autobrr:latest', 'port' => 7474],
    ['slug' => 'autodl', 'name' => 'AutoDL-iRC', 'category' => 'download', 'description' => 'Plugin auto-download IRC pour rTorrent.', 'type' => 'apt', 'package' => 'autodl-irssi'],
    ['slug' => 'btsync', 'name' => 'Resilio Sync', 'category' => 'download', 'description' => 'Synchronisation de fichiers P2P (BitTorrent Sync).', 'type' => 'docker', 'image' => 'resilio/sync', 'port' => 8888],
    ['slug' => 'deluge', 'name' => 'Deluge', 'category' => 'download', 'description' => 'Client BitTorrent avec interface web.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/deluge:latest', 'port' => 8112],
    ['slug' => 'flood', 'name' => 'Flood', 'category' => 'download', 'description' => 'Interface web pour rTorrent.', 'type' => 'docker', 'image' => 'jesec/flood', 'port' => 3000],
    ['slug' => 'jackett', 'name' => 'Jackett', 'category' => 'download', 'description' => 'Proxy API pour indexeurs de torrents.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/jackett:latest', 'port' => 9117],
    ['slug' => 'nzbget', 'name' => 'NZBGet', 'category' => 'download', 'description' => 'Client Usenet léger.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/nzbget:latest', 'port' => 6789],
    ['slug' => 'nzbhydra', 'name' => 'NZBHydra2', 'category' => 'download', 'description' => 'Méta-moteur de recherche Usenet.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/nzbhydra2:latest', 'port' => 5076],
    ['slug' => 'prowlarr', 'name' => 'Prowlarr', 'category' => 'download', 'description' => 'Gestionnaire d\'indexeurs pour *arr.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/prowlarr:latest', 'port' => 9696],
    ['slug' => 'pyload', 'name' => 'pyLoad', 'category' => 'download', 'description' => 'Gestionnaire de téléchargements HTTP/FTP.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/pyload-ng:latest', 'port' => 8000],
    ['slug' => 'qbittorrent', 'name' => 'qBittorrent', 'category' => 'download', 'description' => 'Client BitTorrent avec interface web.', 'type' => 'native'],
    ['slug' => 'qui', 'name' => 'qui', 'category' => 'download', 'description' => 'Interface web pour qBittorrent.', 'type' => 'docker', 'image' => 'ghcr.io/autobrr/qui:latest', 'port' => 7476],
    ['slug' => 'rapidleech', 'name' => 'RapidLeech', 'category' => 'download', 'description' => 'Gestionnaire de liens premium (legacy).', 'type' => 'docker', 'image' => 'ghcr.io/rl-community/rapidleech', 'port' => 80],
    ['slug' => 'rtorrent', 'name' => 'rTorrent', 'category' => 'download', 'description' => 'Client BitTorrent en ligne de commande.', 'type' => 'docker', 'image' => 'linuxserver/rutorrent', 'port' => 80],
    ['slug' => 'rutorrent', 'name' => 'ruTorrent', 'category' => 'download', 'description' => 'Interface web pour rTorrent.', 'type' => 'docker', 'image' => 'linuxserver/rutorrent', 'port' => 80],
    ['slug' => 'sabnzbd', 'name' => 'SABnzbd', 'category' => 'download', 'description' => 'Client Usenet avec interface web.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/sabnzbd:latest', 'port' => 8080],
    ['slug' => 'syncthing', 'name' => 'Syncthing', 'category' => 'download', 'description' => 'Synchronisation de fichiers décentralisée.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/syncthing:latest', 'port' => 8384],
    ['slug' => 'transmission', 'name' => 'Transmission', 'category' => 'download', 'description' => 'Client BitTorrent léger.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/transmission:latest', 'port' => 9091],

    // --- Cloud / stockage ---
    ['slug' => 'calibre', 'name' => 'Calibre', 'category' => 'cloud', 'description' => 'Gestion d\'ebooks et bibliothèque.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/calibre:latest', 'port' => 8080],
    ['slug' => 'calibrecs', 'name' => 'Calibre Content Server', 'category' => 'cloud', 'description' => 'Serveur de contenu Calibre.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/calibre:latest', 'port' => 8081],
    ['slug' => 'calibreweb', 'name' => 'Calibre-Web', 'category' => 'cloud', 'description' => 'Interface web pour bibliothèques Calibre.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/calibre-web:latest', 'port' => 8083],
    ['slug' => 'couchpotato', 'name' => 'CouchPotato', 'category' => 'media', 'description' => 'Gestionnaire de films (legacy).', 'type' => 'docker', 'image' => 'linuxserver/couchpotato', 'port' => 5050],
    ['slug' => 'duckdns', 'name' => 'DuckDNS', 'category' => 'cloud', 'description' => 'Mise à jour dynamique DNS DuckDNS.', 'type' => 'script', 'service' => 'duckdns'],
    ['slug' => 'filebrowser', 'name' => 'File Browser', 'category' => 'cloud', 'description' => 'Gestionnaire de fichiers web.', 'type' => 'docker', 'image' => 'filebrowser/filebrowser:latest', 'port' => 8080],
    ['slug' => 'headphones', 'name' => 'Headphones', 'category' => 'media', 'description' => 'Gestionnaire de musique automatique (legacy).', 'type' => 'docker', 'image' => 'linuxserver/headphones', 'port' => 8181],
    ['slug' => 'mango', 'name' => 'Mango', 'category' => 'cloud', 'description' => 'Lecteur de mangas self-hosted.', 'type' => 'docker', 'image' => 'gotson/komga', 'port' => 25600],
    ['slug' => 'nextcloud', 'name' => 'Nextcloud', 'category' => 'cloud', 'description' => 'Suite bureautique et cloud privé.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/nextcloud:latest', 'port' => 443],
    ['slug' => 'organizr', 'name' => 'Organizr', 'category' => 'cloud', 'description' => 'Page d\'accueil pour vos services.', 'type' => 'docker', 'image' => 'organizr/organizr', 'port' => 80],
    ['slug' => 'rclone', 'name' => 'Rclone', 'category' => 'cloud', 'description' => 'Synchronisation cloud (Google Drive, S3…).', 'type' => 'curl', 'url' => 'https://rclone.org/install.sh'],

    // --- Monitoring ---
    ['slug' => 'librespeed', 'name' => 'LibreSpeed', 'category' => 'monitoring', 'description' => 'Test de débit réseau self-hosted.', 'type' => 'docker', 'image' => 'linuxserver/librespeed', 'port' => 80],
    ['slug' => 'netdata', 'name' => 'Netdata', 'category' => 'monitoring', 'description' => 'Monitoring système en temps réel.', 'type' => 'native'],
    ['slug' => 'netronome', 'name' => 'Netronome', 'category' => 'monitoring', 'description' => 'Monitoring réseau (Swizzin legacy).', 'type' => 'docker', 'image' => 'prom/prometheus:latest', 'port' => 9090],

    // --- Système / réseau ---
    ['slug' => 'csf', 'name' => 'CSF Firewall', 'category' => 'system', 'description' => 'ConfigServer Security & Firewall.', 'type' => 'script', 'service' => 'csf'],
    ['slug' => 'ffmpeg', 'name' => 'FFmpeg', 'category' => 'system', 'description' => 'Encodage et transcodage vidéo/audio.', 'type' => 'apt', 'package' => 'ffmpeg'],
    ['slug' => 'letsencrypt', 'name' => 'Let\'s Encrypt', 'category' => 'system', 'description' => 'Certificats SSL — utilisez le module Sites ObiOra.', 'type' => 'script', 'service' => 'letsencrypt'],
    ['slug' => 'nginx', 'name' => 'Nginx', 'category' => 'system', 'description' => 'Serveur web — géré par ObiOra (module Sites).', 'type' => 'apt', 'package' => 'nginx'],
    ['slug' => 'panel', 'name' => 'Panel Swizzin', 'category' => 'system', 'description' => 'Non applicable — ObiOra remplace le panel Swizzin.', 'type' => 'skip'],
    ['slug' => 'quota', 'name' => 'Quotas disque', 'category' => 'system', 'description' => 'Quotas utilisateurs sur le système de fichiers.', 'type' => 'apt', 'package' => 'quota'],
    ['slug' => 'shellinabox', 'name' => 'Shell In A Box', 'category' => 'system', 'description' => 'Terminal web via HTTPS.', 'type' => 'apt', 'package' => 'shellinabox'],
    ['slug' => 'vsftpd', 'name' => 'vsftpd', 'category' => 'system', 'description' => 'Serveur FTP sécurisé.', 'type' => 'apt', 'package' => 'vsftpd'],
    ['slug' => 'pure-ftpd', 'name' => 'Pure-FTPd', 'category' => 'system', 'description' => 'Serveur FTP léger avec utilisateurs virtuels.', 'type' => 'native'],
    ['slug' => 'webmin', 'name' => 'Webmin', 'category' => 'system', 'description' => 'Administration système via navigateur.', 'type' => 'script', 'service' => 'webmin'],
    ['slug' => 'wireguard', 'name' => 'WireGuard', 'category' => 'network', 'description' => 'VPN moderne et performant.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/wireguard:latest', 'port' => 51820],
    ['slug' => 'x2go', 'name' => 'X2Go', 'category' => 'system', 'description' => 'Bureau à distance Linux.', 'type' => 'apt', 'package' => 'x2goserver'],
    ['slug' => 'xmrig', 'name' => 'XMRig', 'category' => 'tools', 'description' => 'Mineur Monero (CPU/GPU) — usage à vos risques.', 'type' => 'docker', 'image' => 'metal3d/xmrig', 'port' => 8080],
    ['slug' => 'xmr-stak', 'name' => 'XMR-Stak', 'category' => 'tools', 'description' => 'Mineur multi-algo (legacy) — usage à vos risques.', 'type' => 'docker', 'image' => 'metal3d/xmrig', 'port' => 8081],
    ['slug' => 'xmr-stak-cpu', 'name' => 'XMR-Stak CPU', 'category' => 'tools', 'description' => 'Mineur CPU (legacy) — usage à vos risques.', 'type' => 'docker', 'image' => 'metal3d/xmrig', 'port' => 8082],

    // --- Communication ---
    ['slug' => 'quassel', 'name' => 'Quassel', 'category' => 'tools', 'description' => 'Client/serveur IRC.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/quassel-core:latest', 'port' => 4242],
    ['slug' => 'znc', 'name' => 'ZNC', 'category' => 'tools', 'description' => 'Bouncer IRC persistant.', 'type' => 'docker', 'image' => 'lscr.io/linuxserver/znc:latest', 'port' => 6501],
];
