# ObiOra Panel

Plateforme moderne de gestion de serveurs Linux — Laravel 12, Livewire 3, Bootstrap 5.3.

**Version actuelle : v1.0.1** (Phase 1 — Architecture)

## Stack

- Laravel 12 + PHP 8.3+
- Livewire 3
- Bootstrap 5.3 + ApexCharts
- MySQL/MariaDB + Redis
- API REST (Sanctum)
- RBAC (Spatie Permission)

## OS supportés (installation)

| Distribution | Versions |
|---|---|
| Debian | 11, 12 |
| Ubuntu | 20.04, 22.04, 24.04 |
| AlmaLinux | 8, 9, 10 |
| Rocky Linux | 8, 9, 10 |

## Installation (Phase 2)

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)
```

## Développement local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Plan de versions

| Phase | Version | Contenu |
|---|---|---|
| 1 | v1.0.1 | Architecture, modules, migrations core |
| 2 | v1.1.0 | Script d'installation automatique |
| 3 | v1.2.0 | Dashboard + authentification |
| 4 | v1.3.0 | Gestion services Linux |
| 5 | v1.4.0 | Sites web (Nginx, PHP, SSL) |
| 6 | v1.5.0 | Bases de données |
| 7 | v1.6.0 | Docker |
| 8 | v1.7.0 | Sauvegardes |
| 9 | v1.8.0 | Plugins / Marketplace |
| 10 | v1.9.0 | AdminLicence |
| 11 | v2.0.0 | Assistant IA |

## Documentation

- [Architecture Phase 1](docs/architecture/PHASE-1.md)

## Licence

Propriétaire — voir [LICENSE](LICENSE).
