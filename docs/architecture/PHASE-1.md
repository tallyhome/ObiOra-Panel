# ObiOra Panel — Phase 1 : Architecture (v1.0.1)

## Décisions validées

| Choix | Décision |
|---|---|
| Nom | ObiOra Panel |
| Framework | Laravel 12 + PHP 8.3+ |
| UI | Bootstrap 5.3 + Livewire 3 + ApexCharts |
| Modules | Structure custom `Modules/` (pas nwidart) |
| Multi-serveurs | Architecture dès Phase 1 (`servers`, `server_nodes`), UI Phase 3+ |
| Updates | GitHub Releases → AdminLicence (Phase 10) |
| OS | Debian, Ubuntu, AlmaLinux 8-10, Rocky Linux 8-10 |

## Clarification multi-serveurs

Le panel s'installe d'abord sur **un serveur maître** (mode local). Dès Phase 1, la base de données prévoit :

- `servers` — chaque machine gérée (locale ou distante)
- `server_nodes` — connexion agent/SSH

En Phase 3, l'interface permettra d'ajouter des VPS/serveurs distants. En Phase 1, un serveur local est créé automatiquement au seed.

## Arborescence

Voir [README.md](../../README.md) pour l'arborescence complète.

## Modules (23)

Chaque module contient :

- `module.json` — manifest (slug, dépendances, permissions)
- `Providers/{Name}ServiceProvider.php` — bootstrap Laravel

Modules activés par défaut : **Dashboard**, **Servers**, **Updates**.

## Base de données

### Tables core

- `users` (+ is_active, last_login_at)
- `servers`, `server_nodes`
- `panel_modules`, `module_metadata`
- `settings`, `licenses`
- `provisioning_logs`, `update_history`
- `roles`, `permissions` (Spatie)
- `personal_access_tokens` (Sanctum)

## Conventions

- `declare(strict_types=1);` dans tous les fichiers PHP
- PSR-12, PSR-4
- Logique métier dans `App\Services\`
- Opérations système via `SystemExecutorInterface` uniquement
- Channel log dédié : `provisioning`

## Prochaine phase

**Phase 2 (v1.1.0)** — Script `install.sh` complet avec support Debian/Ubuntu/AlmaLinux/Rocky.
