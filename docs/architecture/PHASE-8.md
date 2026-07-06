# ObiOra Panel — Phase 8 : Sauvegardes (v1.7.0)

## Module Backup

Route : `/backups`

| Route | Description |
|---|---|
| `/backups` | Liste des sauvegardes |
| `/backups/create` | Créer une sauvegarde |
| `/backups/{id}` | Détail, restauration SQL, suppression |

## Types de sauvegarde

| Type | Contenu |
|---|---|
| `database` | mysqldump gzip (.sql.gz) |
| `files` | tar.gz d'un répertoire (défaut `/var/www`) |
| `full` | BDD + fichiers web |

Stockage serveur : `/var/backups/obiora`

## Restauration

Disponible pour les sauvegardes **database** (.sql.gz) uniquement.

## API agent

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/v1/backups` | POST | Créer sauvegarde |
| `/api/v1/backups` | DELETE | Supprimer archive |
| `/api/v1/backups/restore` | POST | Restaurer dump SQL |

## Table `backups`

- `server_id`, `name`, `type`, `filename`, `storage_path`
- `size_bytes`, `target`, `status`, `completed_at`
