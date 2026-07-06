# ObiOra Panel — Phase 6 : Bases de données (v1.5.0)

## Module MySQL

Route : `/databases`

| Route | Description |
|---|---|
| `/databases` | Bases gérées + détection serveur |
| `/databases/create` | Création base + utilisateur |
| `/databases/{id}` | Identifiants, DSN .env, suppression |

## Provisionnement

Scripts `agent/scripts/mysql-*.sh` :

1. `mysql-create.sh` — CREATE DATABASE + USER + GRANT
2. `mysql-delete.sh` — DROP DATABASE + USER
3. `mysql-list.sh` — liste via information_schema

Mot de passe généré automatiquement (ou personnalisé), stocké chiffré en base panel.

## Multi-serveurs

| Serveur | Mécanisme |
|---|---|
| Maître | Scripts via `LocalExecutor` + sudo |
| Slave | API agent `/api/v1/databases` |

## API agent

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/v1/databases` | GET | Liste des bases utilisateur |
| `/api/v1/databases` | POST | Créer base + utilisateur |
| `/api/v1/databases` | DELETE | Supprimer base |

## Table `managed_databases`

- `server_id`, `name`, `username`, `password` (chiffré)
- `host`, `charset`, `collation`, `status`

## Prérequis serveur

- MariaDB/MySQL installé
- Accès root (socket ou `/etc/obiora/mysql-admin.cnf`)
- Sudoers agent : `install/lib/sudoers.sh`
