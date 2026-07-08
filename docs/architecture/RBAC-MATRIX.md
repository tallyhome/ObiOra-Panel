# ObiOra Panel — Matrice des droits (RBAC)

Rôles gérés via **Spatie Permission** :

- `super-admin`
- `admin`
- `technician`
- `client`

Définition centralisée : `App\Support\PanelPermissions`  
Seeder : `Database\Seeders\RolePermissionSeeder`

> **Scope client multi-tenant** (propriétaire par app / site / BDD) : **en suspens** — sera lié à AdminLicence dans une version ultérieure.

## Vue d’ensemble

| Zone / action | Permission(s) | super-admin | admin | technician | client |
|---|---|:---:|:---:|:---:|:---:|
| Dashboard | `dashboard.view` | ✓ | ✓ | ✓ | ✓ |
| Marketplace (voir) | `plugins.view` | ✓ | ✓ | ✓ | ✓ |
| Marketplace (installer / désinstaller) | `plugins.manage` | ✓ | ✓ | ✗ | ✓ |
| Services (voir) | `services.view` | ✓ | ✓ | ✓ | ✓ |
| Services (start/stop/restart) | `services.manage` | ✓ | ✓ | ✓ | ✗ |
| Serveurs (voir) | `servers.view` | ✓ | ✓ | ✓ | ✗ |
| Serveurs (créer / supprimer / token) | `servers.manage` | ✓ | ✓ | ✗ | ✗ |
| Monitoring + Doctor | `monitoring.view` | ✓ | ✓ | ✓ | ✗ |
| Sites web (voir) | `websites.view` | ✓ | ✓ | ✓ | ✓ |
| Sites web (gérer) | `websites.manage` | ✓ | ✓ | ✗ | ✓ |
| Bases de données (voir) | `databases.view` | ✓ | ✓ | ✓ | ✓ |
| Bases de données (créer / gérer) | `databases.manage` | ✓ | ✓ | ✗ | ✗ |
| Docker (voir) | `docker.view` | ✓ | ✓ | ✓ | ✗ |
| Docker (gérer) | `docker.manage` | ✓ | ✓ | ✓ | ✗ |
| Sauvegardes (voir) | `backups.view` | ✓ | ✓ | ✓ | ✗ |
| Sauvegardes (créer / restaurer) | `backups.manage` | ✓ | ✓ | ✗ | ✗ |
| Utilisateurs panel (voir) | `users.view` | ✓ | ✗ | ✗ | ✗ |
| Utilisateurs panel (gérer) | `users.manage` | ✓ | ✗ | ✗ | ✗ |
| Infrastructure (voir) | `modules.view` | ✓ | ✓ | ✓ | ✗ |
| Infrastructure (actions) | `modules.manage` | ✓ | ✓ | ✗ | ✗ |
| Licence & MAJ (voir) | `updates.view` | ✓ | ✓ | ✗ | ✗ |
| Lancer MAJ panel | `updates.manage` | ✓ | ✗ | ✗ | ✗ |
| Licence (voir) | `license.view` | ✓ | ✓ | ✗ | ✗ |
| Licence (gérer) | `license.manage` | ✓ | ✗ | ✗ | ✗ |
| Assistant IA | `ai.view` | ✓ | ✓ | ✓ | ✓ |
| Config IA | `ai.manage` | ✓ | ✓ | ✗ | ✗ |
| Mon profil (`/profile`) | *(auth)* | ✓ | ✓ | ✓ | ✓ |

## Règles métier

- `super-admin` possède **toutes** les permissions.
- Impossible de supprimer ou rétrograder le **dernier super-admin**.
- Impossible de désactiver son propre compte.
- La sidebar masque les entrées sans permission (`@can`).
- Les routes web utilisent le middleware `permission:…`.
- Les actions Livewire sensibles appellent `authorizePermission()`.

## Mise à jour en production

Après une montée de version RBAC :

```bash
php artisan db:seed --class=RolePermissionSeeder --force
php artisan permission:cache-reset
```
