# ObiOra Panel — Phase 11 : Temps réel natif (Laravel Reverb)

> Statut : **implémentée (v2.0.0)**. Désactivée par défaut
> (`OBIORA_REALTIME_ENABLED=false`). Le polling Livewire reste disponible en
> repli.

## Objectif

Diffusion instantanée des métriques et évènements via **Laravel Reverb**
(WebSocket natif Laravel), avec repli polling/SSE si Reverb est indisponible.

## Périmètre livré

| Domaine | Implémentation |
|---|---|
| Dashboard | Event `DashboardMetricsUpdated` + Echo → Livewire |
| Services | Event `ServiceStateChanged` après action systemd |
| Marketplace | Event `ProgressUpdated` sur cache progression install |
| Monitoring | Event `MonitoringFleetUpdated` + badge Reverb/SSE |
| Fallback | `wire:poll` dashboard, SSE monitoring si Reverb off |

## Activation sur serveur

```bash
# 1. Mettre à jour le panel
cd /opt/obiora-panel
git fetch --tags && git checkout -f v2.0.0
bash install/update-panel.sh 0 2.0.0
php artisan migrate --force
npm ci && npm run build

# 2. Activer Reverb dans .env
OBIORA_REALTIME_ENABLED=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=obiora-panel
REVERB_APP_KEY=<hex32>
REVERB_APP_SECRET=<hex64>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=<domaine-panel>
REVERB_PORT=8080
REVERB_SCHEME=http   # ou https derrière TLS

# 3. Services
systemctl enable --now obiora-reverb
systemctl restart obiora-queue obiora-scheduler.timer
```

Installation neuve avec Reverb :

```bash
OBIORA_REALTIME_ENABLED=true bash install/install.sh --domain panel.example.com
```

## Nginx WebSocket

Le script `install/lib/reverb.sh` ajoute un bloc `location /app` proxy vers
`127.0.0.1:8080`. Vérifier après SSL :

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
}
```

## Modules stub UI (Phase 11)

Pages dédiées sous `/modules/{slug}` : firewall, ftp, dns, cluster,
virtualizor, users, apache, redis, nginx, ssl, applications, ai.

## Tests

```bash
php artisan test --filter=Realtime
php artisan test --filter=ModuleStub
```

## Prochaine phase

**Phase 13 (v2.1.0+)** — Modules métier Infrastructure, Doctor/Suite, IA enrichie.
Voir [PHASE-13.md](PHASE-13.md).
