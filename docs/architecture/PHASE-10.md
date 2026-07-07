# ObiOra Panel — Phase 10 : AdminLicence & Mises à jour (v1.9.0)

## Routes

| Route | Description |
|---|---|
| `/settings` | Licence ObiOra + vérification / application des mises à jour |

## Licence (AdminLicence)

1. Chaque installation possède un **UUID** unique (table `settings`, groupe `installation`).
2. Saisissez votre clé de licence dans **Licence & MAJ**.
3. Le panel contacte **AdminLicence** (`OBIORA_ADMIN_LICENCE_URL`) pour valider la clé.
4. Si AdminLicence est injoignable, mode gracieux avec validation locale (développement).

### Variables `.env`

```env
OBIORA_LICENSE_ENABLED=false
OBIORA_ADMIN_LICENCE_URL=https://adminlicence.example.com
OBIORA_LICENSE_GRACE_DAYS=7
```

| Variable | Défaut | Description |
|---|---|---|
| `OBIORA_LICENSE_ENABLED` | `false` | Active la validation distante |
| `OBIORA_ADMIN_LICENCE_URL` | — | URL du service AdminLicence |
| `OBIORA_LICENSE_GRACE_DAYS` | `7` | Période de grâce si licence expirée |

## Mises à jour

- Vérification via **GitHub Releases** (`UpdateManager`)
- Application via `git pull` + `composer install` + `migrate` + `optimize`
- Historique dans la table `update_history`
- Disponible sur installation Linux dans `/opt/obiora-panel`

## Plans

| Plan | Serveurs max | Utilisateurs max |
|---|---|---|
| free | 1 | 3 |
| pro | 10 | 25 |
| enterprise | illimité | illimité |

## Prochaine phase

**Phase 11** — Temps réel natif (Laravel Reverb / WebSockets). Voir `PHASE-11.md`.
