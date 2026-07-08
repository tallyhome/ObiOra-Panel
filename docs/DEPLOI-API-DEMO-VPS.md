# Déploiement API démo sur le VPS Panel

Le VPS n'a **pas encore le code** (commande `obiora:site-api-status` absente = ancienne version).
Il faut **déployer** puis configurer le `.env`.

## 1. Depuis votre PC — pousser le code

```bash
cd "I:\Adev\Obiora Panel"
git add routes/api.php app/Http/Middleware/AuthenticateSiteApi.php app/Http/Controllers/Api/DemoAccountController.php app/Services/Demo/ app/Console/Commands/SiteApiStatusCommand.php app/Console/Commands/ExpireDemoAccountsCommand.php app/Models/User.php config/obiora.php routes/console.php database/migrations/2026_07_08_020000_add_demo_fields_to_users_table.php
git commit -m "feat: API démo SiteWeb (comptes temporaires panel)"
git push origin main
```

## 2. Sur le VPS (SSH root@Obiora)

```bash
cd /chemin/vers/obiora-panel    # ex. /var/www/obiora-panel
git pull origin main
composer install --no-dev --optimize-autoloader
```

## 3. Éditer le `.env` du Panel sur le VPS

Ouvrir le fichier `.env` et **ajouter ces 2 lignes** (copier-coller) :

```env
OBIORA_SITE_API_KEY=cfb88d90d8f7bfa73d8734190f7f5ecb197c91355de1ca872c373ab8f6f3f39b
OBIORA_SITE_DEMO_TTL_HOURS=24
```

> Même clé que `OBIORA_PANEL_API_KEY` dans ObiOra-SiteWeb/.env

## 4. Sur le VPS — finaliser

```bash
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan obiora:site-api-status
```

Résultat attendu : `OBIORA_SITE_API_KEY : configurée`

## 5. Sur votre PC (ObiOra-SiteWeb)

```bash
cd ObiOra-SiteWeb
php artisan config:clear
php artisan obiora:check-panel
```

Résultat attendu : `Clé API valide — démo Panel opérationnelle.`

## Connexion SiteWeb (local)

- URL : **http://127.0.0.1:8099/connexion** (doit correspondre à `APP_URL` dans `.env`)
- Admin : `admin@obiora.io` / `password` → `/admin`
- Client : `client@obiora.io` / `password` → `/client`

Si login échoue :
```bash
php artisan obiora:reset-seed-passwords
```

Ne pas confondre avec la connexion **Panel** (obiora.obi2.net) — ce sont des comptes différents.
