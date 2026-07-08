# Déploiement API démo — Panel ↔ SiteWeb

## Le code EST intégré dans le dépôt Git

Routes Panel (`routes/api.php`) :
- `GET /api/v1/site-api/ping`
- `POST /api/v1/demo-accounts`
- `DELETE /api/v1/demo-accounts/{id}`

Le **404** sur le VPS signifie seulement que le serveur n'a **pas encore** ce code (pas de `git pull` / pas de MAJ panel).

---

## Option A — Automatique (recommandé sur le VPS)

Une seule commande après `git pull` :

```bash
cd /opt/obiora-panel
git pull origin main
bash install/setup-site-api.sh
```

Ce script fait tout :
1. `migrate --force`
2. Génère `OBIORA_SITE_API_KEY` dans `.env` si absent (`obiora:setup-site-api --ensure`)
3. `config:clear` + `route:clear`
4. Affiche la clé à copier dans SiteWeb

---

## Option B — MAJ depuis le panel admin

**Administration → Mises à jour → Mettre à jour**

Le script `install/update-panel.sh` appelle automatiquement `obiora:setup-site-api --ensure` après les migrations.

Après la MAJ, récupérez la clé :
```bash
php artisan obiora:setup-site-api
```

---

## `.env` VPS Panel — quoi mettre ?

**Normalement rien à faire à la main** : `--ensure` écrit la clé automatiquement.

Si vous préférez une clé fixe partagée avec le SiteWeb local :

```env
OBIORA_SITE_API_KEY=cfb88d90d8f7bfa73d8734190f7f5ecb197c91355de1ca872c373ab8f6f3f39b
OBIORA_SITE_DEMO_TTL_HOURS=24
```

---

## SiteWeb (PC) — après déploiement Panel

Dans `ObiOra-SiteWeb/.env` :
```env
OBIORA_PANEL_URL=http://obiora.obi2.net
OBIORA_PANEL_API_KEY=<même clé que OBIORA_SITE_API_KEY du panel>
```

```bash
cd "I:\Adev\Obiora Panel\ObiOra-SiteWeb"
php artisan config:clear
php artisan obiora:check-panel
```

---

## Connexion SiteWeb (local)

URL : **http://127.0.0.1:8099/connexion** (identique à `APP_URL`)

```bash
php artisan obiora:reset-seed-passwords
```

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Admin | admin@obiora.io | password |
| Client | client@obiora.io | password |

⚠️ Ne pas faire `cd ObiOra-SiteWeb` si vous y êtes déjà (erreur chemin doublé).
