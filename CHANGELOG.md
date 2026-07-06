# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/).

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
