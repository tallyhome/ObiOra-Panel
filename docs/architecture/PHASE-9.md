# ObiOra Panel — Phase 9 : Marketplace / Plugins (v1.8.0+)

## Concept (inspiré Swizzin)

Chaque application = dossier `packages/{slug}/` :

```
packages/radarr/
├── manifest.json
├── install.sh
└── uninstall.sh
```

Installation en un clic depuis le dashboard (`/plugins`).

## Catalogue Swizzin (v1.8.1)

**68 applications** couvrant le catalogue [swizzin/swizzin](https://github.com/swizzin/swizzin) :

- Scripts **réécrits** pour ObiOra (Docker LinuxServer, APT, curl officiel)
- **Sans copie** du code GPL Swizzin — métadonnées et slugs alignés sur leur dépôt
- 5 apps natives conservées : Netdata, Jellyfin, Plex, Sonarr, qBittorrent

### Génération

```bash
php tools/generate-packages.php
```

Définitions : `tools/swizzin-apps.php`  
Helper partagé : `packages/_lib/docker.sh`

### Catégories

| Catégorie | Exemples |
|---|---|
| media | Radarr, Lidarr, Bazarr, Emby, Tautulli |
| download | Deluge, Transmission, Jackett, Prowlarr |
| cloud | Nextcloud, Calibre-Web, File Browser |
| monitoring | Netdata, LibreSpeed |
| system | Nginx, FFmpeg, Webmin, WireGuard |
| network | WireGuard |
| tools | ZNC, Quassel |

## Architecture

- `ApplicationCatalog` — scan `packages/*/manifest.json`
- `ApplicationManager` — install/uninstall (local + agent)
- Table `installed_applications` par serveur
- API agent : `/api/v1/applications/install|uninstall`

## Ajouter une app

1. Ajouter une entrée dans `tools/swizzin-apps.php` (ou créer manuellement)
2. Lancer `php tools/generate-packages.php` ou créer `packages/mon-app/` à la main
3. L'app apparaît automatiquement dans le marketplace
