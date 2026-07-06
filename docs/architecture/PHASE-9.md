# ObiOra Panel — Phase 9 : Marketplace / Plugins (v1.8.0)

## Concept (inspiré Swizzin)

Chaque application = dossier `packages/{slug}/` :

```
packages/netdata/
├── manifest.json
├── install.sh
└── uninstall.sh
```

Installation en un clic depuis le dashboard — **sans** recopier les 100 scripts Swizzin (licence GPL).

## Route

`/plugins` — Marketplace

## Catalogue initial (v1.8.0)

| App | Catégorie | Méthode |
|---|---|---|
| Netdata | monitoring | script officiel |
| Jellyfin | media | dépôt APT |
| Plex | media | Docker |
| Sonarr | media | Docker |
| qBittorrent | download | Docker |

## Architecture

- `ApplicationCatalog` — scan `packages/*/manifest.json`
- `ApplicationManager` — install/uninstall (local + agent)
- Table `installed_applications` par serveur
- API agent : `/api/v1/applications/install|uninstall`

## Ajouter une app

1. Créer `packages/mon-app/manifest.json`
2. Ajouter `install.sh` et `uninstall.sh`
3. L'app apparaît automatiquement dans le marketplace
