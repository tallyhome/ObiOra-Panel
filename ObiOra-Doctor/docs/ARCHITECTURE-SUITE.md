# Obiora Suite — Architecture complète

Document de référence pour **ObiOra Panel SeedBox** + **ObiOra Doctor** + **Obiora Monitor**.

---

## 1. Vue d'ensemble

```
                    ┌──────────────────────────────────────┐
                    │     ObiOra Panel SeedBox (hub)       │
                    │  /monitoring  — Dashboard Vue.js     │
                    │  SSE temps réel + graphiques         │
                    │  Alertes email (SSL, critiques)      │
                    │  API diagnostics + vérification HMAC │
                    └───────────────┬──────────────────────┘
                                    │ HTTPS
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
        ┌───────────┐         ┌───────────┐         ┌───────────┐
        │  VPS #1   │         │  VPS #2   │         │  Dédié    │
        │  Agent    │         │  Agent    │         │ Virtualizor│
        │  léger    │         │  léger    │         │  + Agent   │
        └───────────┘         └───────────┘         └───────────┘
```

**Principe** : un seul panel lourd. Sur chaque serveur monitoré : uniquement l'agent Python (`install-agent.sh`).

---

## 2. Composants

| Composant | Rôle | Emplacement |
|-----------|------|-------------|
| **ObiOra Panel** | UI, fleet, alertes, historique | Serveur maître |
| **ObiOra Doctor** | 30 modules diagnostic | Agent `/opt/obiora-agent` |
| **Obiora Monitor** | Dashboard Vue + SSE + ApexCharts | `/monitoring` dans le panel |
| **Agent systemd** | Scan périodique + push JSON | `obiora-agent.service` |

---

## 3. Modules Doctor (30)

`cpu`, `ram`, `swap`, `disk`, `smart`, `raid`, `network`, `kernel`, `reboot`, `docker`, `kvm`, `lxc`, `virtualizor`, `mysql`, `postgresql`, `php`, `apache`, `nginx`, `litespeed`, `laravel`, `cpanel`, `plesk`, `directadmin`, `firewall`, `security`, `ssl`, `redis`, `memcached`, `whmcs`, `benchmark`

### WHMCS

WHMCS = logiciel de **facturation clients** pour hébergeurs. Le module détecte son installation et le cron — utile seulement si WHMCS tourne sur ce serveur.

### Nouveautés v0.5.0

- **MySQL slow queries** : compteur `Slow_queries`, `long_query_time`, état du slow log
- **SSL alertes** : WARNING < 30 jours, CRITICAL si expiré
- **Diff métriques** : `compare` inclut les changements dans `metrics`
- **Plugin store** : catalogue JSON + `obiora-doctor plugins install <id>`

---

## 4. Installation agent sur un VPS

### Prérequis panel

```bash
php artisan migrate
# Copier la clé signing affichée à l'install dans .env :
# OBIORA_DOCTOR_SIGNING_KEY=<64_chars_hex>
```

### Sur le VPS Linux

```bash
OBIORA_PANEL_URL=https://panel.example.com \
OBIORA_SERVER_ID=2 \
OBIORA_AGENT_TOKEN=token_du_serveur \
OBIORA_SIGNING_KEY=meme_cle_que_panel \
bash ObiOra-Doctor/install/install-agent.sh
```

Le script :
1. Copie l'agent dans `/opt/obiora-agent`
2. Crée `config/agent-panel.json` (URL, ID, token, signing_key)
3. Active `obiora-agent.service` (scan toutes les 5 min)

---

## 5. API Panel

### Agent → Panel (Bearer `agent_token`)

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/v1/servers/{id}/diagnostics/reports` | Rapport complet signé HMAC |
| POST | `/api/v1/servers/{id}/diagnostics/heartbeat` | Ping léger (score, hostname) |

### UI authentifiée (session)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/monitoring/fleet` | Snapshot fleet + alertes |
| GET | `/api/monitoring/stream` | **SSE** temps réel (ping + fleet) |
| GET | `/api/monitoring/servers/{id}/ping-history` | Historique latence ICMP |
| GET | `/api/monitoring/servers/{id}/score-history` | Historique scores Doctor |
| GET | `/api/monitoring/servers/{id}/compare?left=1&right=2` | Diff rapports + métriques |

---

## 6. Signature cryptographique

1. L'agent signe le rapport avec **HMAC-SHA256**
2. Clé partagée : `OBIORA_DOCTOR_SIGNING_KEY` (panel) = `signing_key` (agent-panel.json)
3. Le panel vérifie à l'ingestion et stocke `signature_verified`
4. Option stricte : `OBIORA_DOCTOR_REQUIRE_SIGNATURE=true` → rejette les rapports non signés

Algorithme identique Python/PHP : `json.dumps(data, sort_keys=True)` puis HMAC.

---

## 7. Ping temps réel

| Mécanisme | Intervalle | Description |
|-----------|----------|-------------|
| **Scheduler** | 30 s | `php artisan obiora:monitor-ping` — ICMP puis fallback TCP:9100 |
| **SSE stream** | 5 s | Push fleet vers le navigateur via EventSource |
| **Dashboard Vue** | Live | Badge « Live » + graphiques ApexCharts |

Activer le scheduler (production) :

```bash
* * * * * cd /path/to/panel && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. Alertes email

Types d'alertes :
- Findings **CRITICAL** Doctor
- Certificats SSL expirés / < 30 jours
- Serveur **offline** (ping échoué)
- Signature **invalide** (mode strict)

Envoi : `php artisan obiora:monitor-alerts` (planifié toutes les 5 min)

Configurer SMTP dans `.env` (`MAIL_MAILER=smtp`, etc.).

---

## 9. Dashboard Monitor (Vue.js)

Page `/monitoring` :
- Cartes résumé (serveurs, en ligne, score moyen, alertes)
- Tableau fleet avec ping ms, score, signature, critiques
- Graphiques latence ping + historique score (clic « Détails »)
- Connexion SSE pour mise à jour live

Build front :

```bash
npm install && npm run build
```

---

## 10. Plugin store

Catalogue local : `config/plugin-catalog.json`

```bash
./obiora.sh
# ou
python bin/obiora-doctor.py plugins list
python bin/obiora-doctor.py plugins install example-health
python bin/obiora-doctor.py plugins installed
```

Plugins dans `plugins/*.py` — découverte automatique au scan.

---

## 11. Test bout-en-bout (checklist)

1. [ ] Panel migré (`php artisan migrate`)
2. [ ] Serveur créé dans `/servers/create` avec `agent_token`
3. [ ] `OBIORA_DOCTOR_SIGNING_KEY` dans `.env` panel
4. [ ] Agent installé sur VPS Linux avec même signing key
5. [ ] `systemctl status obiora-agent` → actif
6. [ ] `/monitoring` affiche le serveur avec score
7. [ ] Graphiques ping/score après clic Détails
8. [ ] Badge « Live » vert (SSE connecté)
9. [ ] `php artisan obiora:monitor-ping` → latence affichée
10. [ ] Rapport signé → colonne Signature « OK »

---

## 12. Variables d'environnement

| Variable | Défaut | Description |
|----------|--------|-------------|
| `OBIORA_DOCTOR_SIGNING_KEY` | — | Clé HMAC hex 64 chars |
| `OBIORA_DOCTOR_REQUIRE_SIGNATURE` | false | Rejeter rapports non signés |
| `OBIORA_MONITOR_PING_INTERVAL` | 30 | Intervalle ping scheduler (s) |
| `OBIORA_MONITOR_HISTORY_HOURS` | 24 | Historique graphiques |
| `OBIORA_MONITOR_ALERTS_EMAIL` | true | Alertes mail actives |
| `OBIORA_MONITOR_STREAM_INTERVAL` | 5 | Intervalle SSE (s) |

---

## 13. CI

GitHub Actions : `.github/workflows/obiora-doctor.yml`
- Tests Python (`unittest`)
- ShellCheck sur les scripts bash

---

## 14. Fichiers clés

| Fichier | Rôle |
|---------|------|
| `ObiOra-Doctor/core/agent.py` | Boucle agent |
| `ObiOra-Doctor/core/panel_client.py` | Push HTTP |
| `ObiOra-Doctor/core/signing.py` | HMAC |
| `app/Services/Diagnostics/ReportSignatureVerifier.php` | Vérif panel |
| `app/Services/Monitoring/ServerPingService.php` | ICMP/TCP |
| `resources/js/monitoring/MonitoringDashboard.vue` | Dashboard Vue |
| `routes/console.php` | Scheduler monitoring |

---

*Obiora Suite v0.5.0 — ObiOra Panel*
