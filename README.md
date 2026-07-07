# ObiOra Panel

Plateforme moderne de gestion de serveurs Linux — Laravel 12, Livewire 3, Bootstrap 5.3.

**Version actuelle : v1.9.1**

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

Options : `--domain`, `--email`, `--docker`, `--ftp`, `--tag`, `--dir`

Voir [docs/architecture/PHASE-2.md](docs/architecture/PHASE-2.md)

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
| 3 | v1.2.0 | Dashboard Livewire + Auth + Multi-serveurs |
| 4 | v1.3.0 | Slave installer + Services systemd |
| 5 | v1.4.0 | Sites web (Nginx, PHP, SSL) |
| 6 | v1.5.0 | Bases de données MySQL/MariaDB |
| 7 | v1.6.0 | Docker |
| 8 | v1.7.0 | Sauvegardes |
| 9 | v1.8.0 | Plugins / Marketplace |
| 10 | **v1.9.0** | **AdminLicence & Mises à jour** |
| 11 | v2.0.0 | Assistant IA |

## Documentation

- [Architecture Phase 1](docs/architecture/PHASE-1.md)
- [Installation Phase 2](docs/architecture/PHASE-2.md)
- [Dashboard & Multi-serveurs Phase 3](docs/architecture/PHASE-3.md)
- [Services & Slave Phase 4](docs/architecture/PHASE-4.md)
- [Sites web Phase 5](docs/architecture/PHASE-5.md)
- [Bases de données Phase 6](docs/architecture/PHASE-6.md)
- [Docker Phase 7](docs/architecture/PHASE-7.md)
- [Sauvegardes Phase 8](docs/architecture/PHASE-8.md)
- [Marketplace Phase 9](docs/architecture/PHASE-9.md)
- [AdminLicence Phase 10](docs/architecture/PHASE-10.md)

## Licence

Propriétaire — voir [LICENSE](LICENSE).
