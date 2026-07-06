# ObiOra Panel — Phase 5 : Sites web (v1.4.0)

## Module Websites

Route : `/websites`

| Route | Description |
|---|---|
| `/websites` | Liste des sites du serveur actif |
| `/websites/create` | Création (Nginx + PHP-FPM) |
| `/websites/{id}` | Détail, SSL, suppression |

## Provisionnement

Lors de la création d'un site :

1. Répertoire `/var/www/{domain}/public` + page d'accueil PHP
2. Vhost Nginx `obiora-{domain}` (sites-available + symlink)
3. Reload Nginx
4. Métadonnées `.obiora.json` sur le serveur

## SSL Let's Encrypt

- À la création (option) ou depuis la fiche site
- `certbot --nginx` avec redirection HTTPS
- Date d'expiration stockée en base

## Multi-serveurs

| Serveur | Mécanisme |
|---|---|
| Maître local | Scripts `agent/scripts/*.sh` via `LocalExecutor` |
| Slave distant | API agent `/api/v1/websites` |

## API agent (nouveaux endpoints)

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/v1/websites` | GET | Liste sites (fichiers `.obiora.json`) |
| `/api/v1/websites` | POST | Créer un site |
| `/api/v1/websites` | DELETE | Supprimer un site |
| `/api/v1/websites/ssl` | POST | Activer SSL |

## Table `websites`

- `server_id`, `domain`, `document_root`, `php_version`
- `ssl_enabled`, `ssl_expires_at`, `ssl_email`
- `status`, `nginx_config_path`, `metadata`
