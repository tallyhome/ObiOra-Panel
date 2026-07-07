# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/).

## v1.9.20 - 2026-07-07

Corrige l'échec d'installation du helper setuid (`OBIORA_GROUP: unbound variable`) qui bloquait toute mise à jour. Le git sync s'exécute désormais avant l'installation du helper pour débloquer les serveurs coincés.

## v1.9.19 - 2026-07-07

Champs texte formulaires enfin lisibles (variables Bootstrap compilées avant import + classe obiora-input). Sauvegarde BDD corrigée (pipefail grep vide). Installation Docker avec barre de progression % et correction cache DNF corrompu.

## v1.9.18 - 2026-07-07

Correction définitive des mises à jour panel : binaire setuid `/usr/local/bin/obiora-panel-update` (plus de dépendance sudoers). Bouton « Installer Docker » dans le panel. Champs formulaire serveurs lisibles (texte visible sur fond sombre). Docker installé par défaut à l'installation.

## v1.9.17 - 2026-07-07

Corrige sauvegardes (`tar` + PATH sudo), sites web sur AlmaLinux/RHEL (`conf.d` au lieu de `sites-available`), marketplace install/désinstall via `marketplace-exec.sh`, et filtre les services panel (masque auditd et services système non gérables).

## v1.9.16 - 2026-07-07

Corrige « Échec de la mise à jour : sudo: a password is required » : le worker `obiora-queue` (utilisateur `obiora`) peut désormais exécuter `update-panel.sh` sans mot de passe, et l'ID de progression est passé en argument (plus via `env` qui bloquait sudoers).

## v1.9.15 - 2026-07-07

Corrige `sudo: a password is required` pour sauvegardes, sites web, bases MySQL, Docker et services : les scripts agent sont exécutés directement (plus via `bash`) pour correspondre aux règles sudoers. Ajout de scripts `systemctl-action.sh` et `systemctl-logs.sh` pour start/stop et journaux. Masquage des services systemd internes dans la liste.

## v1.9.14 - 2026-07-07

Corrige l'échec `vite build` sur les serveurs déjà installés : `npm ci`/`npm install` est désormais toujours exécuté avant `npm run build` (dépendance `sweetalert2` manquante dans node_modules).

## v1.9.13 - 2026-07-07

Logo SVG intégré directement dans le HTML (plus de fichier externe — fonctionne sur tous les serveurs). Widgets RAM et disque refaits style QuickBox avec icônes et libellés « utilisé / libre / total ». Correction du bouton « Mettre à jour » (SweetAlert + Livewire) et barre de progression avec % pendant l'installation depuis GitHub.

## v1.9.12 - 2026-07-07

Corrige le logo qui ne s'affichait pas après MAJ (SELinux bloquait silencieusement les nouveaux fichiers non relabellisés : `restorecon` ajouté à update-panel.sh). Le worker de file d'attente (`obiora-queue`) est désormais démarré automatiquement par le panel si besoin — plus aucune commande SSH à taper côté client.

## v1.9.11 - 2026-07-07

Corrige le bouton « Mettre à jour » qui ne faisait rien : la MAJ tournait de façon synchrone dans la requête HTTP et dépassait les timeouts PHP-FPM/Nginx (composer+npm+migrate peuvent prendre plusieurs minutes). Bascule sur une file d'attente (`obiora-queue`) : le clic lance immédiatement le job en arrière-plan, avec suivi en direct (statut, spinner, historique) via un polling toutes les 3 secondes.

## v1.9.10 - 2026-07-07

Logo SVG ObiOra Panel + SeedBox (sidebar, page login). Note explicative sur l'historique MAJ failed.

## v1.9.9 - 2026-07-07

Corrige le bouton Verifier (toast + chargement) et detecte les tags GitHub sans release.

## v1.9.8 - 2026-07-07

Dashboard bande passante temps reel et layout Swizzin.

## v1.9.7 - 2026-07-07

Corrige les echecs de MAJ quand le depot serveur a des fichiers modifies localement (git reset --hard origin/main).

## v1.9.6 - 2026-07-07

Corrige l'erreur **Call to undefined method ProcessResult::output()** lors du clic Mettre a jour sur la page Licence et MAJ.

## v1.9.5 - 2026-07-07

### Correctifs

- Sites web / Bases / Docker : scripts agent via sudo -n (PHP-FPM apache)
- Sudoers apache/nginx sur agent/scripts + /var/www
- Suppression des entrees error/pending meme si deprovisionnement echoue
- Provisionnement website-create.sh (permissions, socket PHP Remi)

### Ameliorations

- SweetAlert2 pour toasts et confirmations
- update-panel.sh reapplique sudoers automatiquement

## v1.9.4 - 2026-07-07

Corrige erreur 500 lors de la mise a jour depuis le panel. Script install/update-panel.sh + sudoers pour PHP-FPM. Dashboard refresh 10s.

## v1.9.3 - 2026-07-07

Dashboard refonte style Swizzin/QuickBox: theme sombre, widgets systeme, barres de progression, services cles, auto-refresh 30s.

## v1.9.2 - 2026-07-07

Corrige la detection des mises a jour (VERSION, git, GitHub API) et affiche un bandeau + badge sidebar.

## v1.9.0 - 2026-07-07

Phase 10: page Licence et MAJ, integration AdminLicence, correctifs installateur (404, permissions, SELinux).

## [1.9.9] - 2026-07-07

### Corrigé

- **Vérifier les MAJ** : toast SweetAlert2 + indicateur de chargement + horodatage de la dernière vérification
- **Détection MAJ** : prise en compte des tags GitHub (ex. v1.9.8) même sans release publiée

## [1.9.8] - 2026-07-07

### Amélioré

- **Dashboard** : graphique bande passante temps réel (poll 3s) au-dessus de la charge CPU
- **Layout Swizzin** : colonne droite avec En un coup d'œil, RAM, Disque, puis Network Info (Interface, Span, historique journalier)

## [1.9.7] - 2026-07-07

### Corrigé

- **update-panel.sh** : `git reset --hard origin/main` si le dépôt a des modifications locales (évite l'échec du pull sur le serveur)

## [1.9.6] - 2026-07-07

### Corrigé

- **Mise à jour panel (500)** : `PanelUpdater` utilisait `output()` / `successful()` comme méthodes au lieu des propriétés de `ProcessResult`

## [1.9.5] - 2026-07-07

### Corrigé

- **Sites web / Bases / Docker** : scripts agent exécutés via `sudo -n` (PHP-FPM `apache` sans mot de passe)
- **Sudoers** : `apache`/`nginx` autorisés sur `agent/scripts/*.sh` + création `/var/www` à l'install/MAJ
- **Suppression** : entrées en erreur ou en attente retirables même si le déprovisionnement serveur échoue
- **Provisionnement** : `website-create.sh` corrigé (permissions, socket PHP Remi/RHEL)

### Amélioré

- **Notifications** : SweetAlert2 (toasts + confirmations) à la place des `alert` / `wire:confirm` natifs
- **MAJ panel** : `update-panel.sh` réapplique la configuration sudoers automatiquement

## [1.9.4] - 2026-07-07

### Corrigé

- **Mise à jour panel (500)** : import manquant + script `install/update-panel.sh` exécuté via sudo (PHP-FPM ne peut pas faire git/composer directement)
- **Sudoers** : autorise `apache`/`nginx` à lancer `update-panel.sh` sans mot de passe
- **Dashboard** : rafraîchissement auto toutes les **10 secondes** (badge Live)

## [1.9.3] - 2026-07-07

### Amélioré

- **Dashboard seedbox** : thème sombre inspiré Swizzin / QuickBox (widgets, barres de progression, uptime, services clés)
- **Navigation** : sidebar repensée, raccourcis Marketplace / Services / Sites / Docker
- **Auto-refresh** : métriques actualisées toutes les 30 secondes

## [1.9.2] - 2026-07-07

### Corrigé

- **Mises à jour** : détection fiable via fichier `VERSION`, tag git et commits en retard sur `main`
- **GitHub API** : en-têtes requis + repli sur la liste des releases si `/releases/latest` échoue
- **UI** : bandeau « Mise à jour disponible », badge `!` dans la sidebar, messages d'erreur API visibles
- **PanelUpdater** : `config:clear` après mise à jour, détection hors strict `/opt/`

## [1.9.1] - 2026-07-07

### Corrigé

- **Serveurs** : page détail « Local Server » — vue `server-show` complète (corrige erreur 500)
- **Sites web / Bases de données** : nouvelle tentative possible après échec (suppression des entrées `pending`/`error` fantômes)

## [1.9.0] - 2026-07-07

### Phase 10 — AdminLicence & Mises à jour

#### Ajouté

- Page **Licence & MAJ** (`/settings`) : activation licence, sync AdminLicence, vérification GitHub Releases
- `AdminLicenceClient`, `LicenseService`, `PanelUpdater`
- Historique des mises à jour (`update_history`)
- Menu sidebar « Licence & MAJ »
- Documentation [PHASE-10.md](docs/architecture/PHASE-10.md)

#### Corrigé (installateur v1.8.10 → inclus en 1.9.0)

- **404 « File not found »** : permissions web (`nginx`/`apache` dans groupe `obiora`), détection socket PHP-FPM Remi/RHEL, SELinux
- Sync IP/hostname du serveur maître après installation
- Message post-install enrichi (URL `/setup`, étapes suivantes)

## [1.8.9] - 2026-07-07

### Corrigé

- **systemd timer** : `OnCalendar=minutely` à la place de `* * * * *` (syntaxe cron invalide) — corrige `bad unit file setting` sur AlmaLinux
- **Nginx** : `default_server` + désactivation de `default.conf` RHEL — corrige le conflit `server_name "_"`
- **Réinstallation** : conserve `APP_KEY` et saute npm si les assets sont déjà compilés
- **Supervisor** : démarrage optionnel (n'interrompt plus l'install)

## [1.8.8] - 2026-07-07

### Corrigé

- **Nginx sur RHEL/AlmaLinux** : écriture directe dans `/etc/nginx/conf.d/` quand `sites-available/` n'existe pas (convention Debian) — corrige `No such file or directory` sur AlmaLinux

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
