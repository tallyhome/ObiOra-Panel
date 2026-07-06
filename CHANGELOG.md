# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/).

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
