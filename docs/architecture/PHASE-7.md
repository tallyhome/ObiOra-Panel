# ObiOra Panel — Phase 7 : Docker (v1.6.0)

## Module Docker

Route : `/docker`

| Fonction | Description |
|---|---|
| Vue d'ensemble | Version Docker, conteneurs actifs, images |
| Conteneurs | Liste, start/stop/restart/remove, logs |
| Images | Liste et suppression |
| Run | Lancer un conteneur (image, nom, ports) |

Fonctionne sur le **serveur actif** (maître local ou slave via agent).

## Scripts agent

- `docker-info.sh` — version et compteurs
- `docker-containers.sh` — liste conteneurs
- `docker-images.sh` — liste images
- `docker-action.sh` — start/stop/restart/remove
- `docker-logs.sh` — logs conteneur
- `docker-run.sh` — docker run -d
- `docker-rmi.sh` — suppression image

## API agent

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/v1/docker/info` | GET | Infos Docker |
| `/api/v1/docker/containers` | GET | Liste conteneurs |
| `/api/v1/docker/images` | GET | Liste images |
| `/api/v1/docker/containers/logs` | GET | Logs |
| `/api/v1/docker/containers/action` | POST | Action conteneur |
| `/api/v1/docker/containers/run` | POST | Lancer conteneur |
| `/api/v1/docker/images` | DELETE | Supprimer image |

## Prérequis

- Docker installé (`install.sh --docker` ou manuellement)
- Sudoers agent pour scripts (`install/lib/sudoers.sh`)
