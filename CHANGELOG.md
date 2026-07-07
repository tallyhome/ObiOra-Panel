# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/).

## [1.8.7] - 2026-07-07

### Corrigé

- **Redis Connection refused** : Redis est démarré **avant** les migrations (auparavant lancé à `setup_systemd`, après `setup_laravel`) — corrige l'échec de la migration `permission_tables` (reset de cache spatie sur `CACHE_STORE=redis`)

## [1.8.6] - 2026-07-07

### Corrigé

- **DB Access denied** : `ALTER USER` force la synchro du mot de passe (au lieu de `CREATE USER IF NOT EXISTS` qui l'ignore si l'utilisateur existe), et réutilisation du mot de passe existant sur réinstallation
- **Host DB** : création de l'utilisateur pour `localhost` **et** `127.0.0.1` (connexion TCP Laravel)

## [1.8.5] - 2026-07-07

### Corrigé

- **composer.lock incompatible PHP 8.3** : plateforme figée à `php 8.3.0` dans `composer.json`, lock régénéré vers Symfony 7.x — corrige `requires php >=8.4.1 -> your php version (8.3.32) does not satisfy` sur AlmaLinux
- **Réinstallation** : mise à jour du dépôt robuste (`git checkout -B main origin/main`) — corrige `pathspec 'main' did not match` sur un ancien clone shallow

## [1.8.4] - 2026-07-07

### Corrigé

- **composer: command not found** : `setup_laravel` préserve désormais le `PATH` (incluant `/usr/local/bin`) lors des commandes exécutées via `sudo -u obiora` — corrige l'échec sur AlmaLinux/RHEL (secure_path)
- **Installation sur `main`** : `OBIORA_TAG` vide par défaut — évite le warning `is not a commit` et l'état `detached HEAD` liés aux tags annotés

## [1.8.3] - 2026-07-07

### Corrigé

- **clone_panel** : `git clone` en root vers `/opt/obiora-panel` (l'utilisateur `obiora` ne peut pas créer de dossier dans `/opt`) — corrige `Permission denied` en cas de réinstallation

## [1.8.2] - 2026-07-06

### Corrigé

- **Installateur one-liner** : détection explicite `/dev/fd/*` — corrige l'erreur `/dev/fd/lib/common.sh`
- Même correctif appliqué à `Slave/install.sh`

## [1.8.1] - 2026-07-06

### Catalogue Swizzin + correctif installateur

#### Ajouté

- **68 applications** marketplace (catalogue Swizzin complet)
- Générateur `tools/generate-packages.php`
- Helper Docker partagé `packages/_lib/docker.sh`

#### Corrigé

- Bootstrap install.sh (première version auto-clone)

## [1.8.0] - 2026-07-06

### Phase 9 — Marketplace / Plugins

#### Ajouté

- Marketplace `/plugins` — installation apps en un clic depuis le dashboard
- Catalogue extensible `packages/` (style Swizzin, réécriture propriétaire)
- Apps initiales : Netdata, Jellyfin, Plex, Sonarr, qBittorrent
- `ApplicationCatalog`, `ApplicationManager`, table `installed_applications`
- API agent : `/api/v1/applications/install` et `uninstall`
- Sudoers étendu pour scripts `packages/*/install.sh`
- Menu « Marketplace » dans la sidebar

## [1.7.0] - 2026-07-06

### Phase 8 — Sauvegardes

#### Ajouté

- Module Backup : création, liste, détail, suppression
- Types : base de données, fichiers, complète
- Restauration SQL pour dumps `.sql.gz`
- Table `backups` liée aux serveurs
- `BackupManager` et `BackupProvisioner` (local + agent distant)
- Scripts `agent/scripts/backup-*.sh`
- API agent : `/api/v1/backups` et `/api/v1/backups/restore`
- Menu « Sauvegardes » dans la sidebar

## [1.6.0] - 2026-07-06

### Phase 7 — Docker

#### Ajouté

- Module Docker : conteneurs, images, logs, run
- `DockerManager` local + distant via agent
- Scripts `agent/scripts/docker-*.sh`
- API agent : endpoints `/api/v1/docker/*`
- Menu « Docker » dans la sidebar
- Formulaire rapide `docker run` (image, nom, ports)

## [1.5.0] - 2026-07-06

### Phase 6 — Bases de données MySQL/MariaDB

#### Ajouté

- Module MySQL : création, liste, détail, suppression de bases
- Scripts `agent/scripts/mysql-*.sh` (create, delete, list)
- Table `managed_databases` avec mots de passe chiffrés
- `DatabaseManager` et `DatabaseProvisioner` (local + agent distant)
- API agent : endpoints `/api/v1/databases`
- Menu « Bases de données » dans la sidebar
- Sudoers agent (`install/lib/sudoers.sh`) pour scripts sans mot de passe

## [1.4.0] - 2026-07-06

### Phase 5 — Sites web (Nginx, PHP, SSL)

#### Ajouté

- Module Websites : création, liste, détail, suppression
- Provisionnement Nginx + PHP-FPM via scripts `agent/scripts/`
- SSL Let's Encrypt (certbot) à la création ou après coup
- Table `websites` liée aux serveurs
- `WebsiteManager` et `WebsiteProvisioner` (local + agent distant)
- API agent : endpoints `/api/v1/websites` et `/api/v1/websites/ssl`
- Menu « Sites web » dans la sidebar

#### Installation

**Master :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)







```
**Slave :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)







```
## [1.3.0] - 2026-07-06

### Phase 4 — Slave installer & Services systemd

#### Ajouté

- Répertoire `Slave/` avec installateur one-liner pour serveurs distants
- Génération automatique de clé API sur le slave
- Liaison maître par clé API (plus de token généré côté maître)
- Module Services : liste, start/stop/restart, logs journalctl
- `ServiceManager` local + distant via agent
- API agent étendue : services, logs, ping enrichi (hostname, IP, OS)
- Menu Services dans la sidebar

#### Installation

**Master :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)







```
**Slave :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)







```
## [1.2.0] - 2026-07-06

### Phase 3 — Dashboard, Auth & Multi-serveurs

#### Ajouté

- Authentification Livewire (login, logout, rate limiting)
- Wizard setup premier admin (`/setup`)
- Dashboard Livewire avec métriques système et ApexCharts
- Module multi-serveurs : liste, ajout, ping, sélecteur de serveur actif
- `ServerManager`, `MetricsCollector`, `AgentExecutor`
- Agent HTTP (`agent/public/index.php`) — ping + métriques
- Layout Bootstrap 5.3 avec sidebar
- Tests Feature setup/auth
- Sync token agent à l'installation

## [1.1.0] - 2026-07-06

### Phase 2 — Installation automatique

#### Ajouté

- Script `install/install.sh` complet (one-liner curl)
- Modules bash : detect-os, prerequisites, packages, database, laravel, nginx, ssl, systemd, firewall, rollback
- Support Debian, Ubuntu, AlmaLinux, Rocky Linux
- Installation : Nginx, PHP 8.3, MariaDB, Redis, Composer, Node 20, Supervisor, Certbot, Fail2Ban, UFW/firewalld
- Options `--docker`, `--ftp`, `--domain`, `--email`, `--tag`
- Services systemd : queue worker, scheduler, agent
- Script `install/uninstall.sh`
- `composer.lock` et `package-lock.json` générés

## [1.0.1] - 2026-07-06

### Phase 1 — Architecture

#### Ajouté

- Structure Laravel 12 avec architecture modulaire custom (`Modules/`)
- 23 modules stub (Dashboard, Servers, Services, Websites, Nginx, Apache, PHP, MySQL, Redis, Docker, Firewall, FTP, DNS, SSL, Backup, Monitoring, Users, Applications, Plugins, Cluster, Virtualizor, Updates, AI)
- Système core : `ModuleManager`, `UpdateManager`, `LicenseManager`, `ApplicationInstaller`
- Couche d'exécution système : `LocalExecutor` + contrat `SystemExecutorInterface`
- Migrations core : serveurs, nœuds, modules, licences, settings, logs, historique updates
- RBAC avec Spatie Permission (rôles : super-admin, admin, technician, client)
- API health endpoint `/api/v1/health`
- Frontend Bootstrap 5.3 + ApexCharts (préparation Livewire Phase 3)
- Stubs installation (`install/`) et agent (`agent/`)
- Support OS documenté : Debian, Ubuntu, AlmaLinux, Rocky Linux
- Licence propriétaire ObiOra

#### Notes

- Mises à jour via GitHub Releases (AdminLicence en Phase 10)
- Dashboard complet et authentification : Phase 3 (v1.2.0)
