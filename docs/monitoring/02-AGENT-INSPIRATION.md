# Agent Pinguzo — Inspiration (lecture seule)

> **Important** : ne pas recopier ni redistribuer le code Pinguzo. Ce document décrit l’architecture publique et comment **inspecter localement** l’installation sur le dédié Virtualizor pour s’en inspirer.

---

## Pourquoi on ne « récupère » pas l’agent

| Raison | Détail |
|--------|--------|
| Propriétaire | Softaculous / Pinguzo — pas open source |
| Couplage cloud | Clé 64 hex → edge `*.pinguzo.com` uniquement |
| Incompatible panel | Les métriques partent chez eux, pas vers ObiOra |
| Maintenance | Mises à jour auto depuis `s2.softaculous.com` |
| Légal | Reverse engineering / redistribution interdits |

**ObiOra aura son propre agent Monitor** (Phase 3), inspiré des **patterns**, pas du code.

---

## Architecture Pinguzo (doc publique + log install)

```
┌─────────────────┐     HTTPS POST      ┌──────────────────┐
│  pinguzo-agent  │ ──────────────────► │  Edge (uk.*.com) │
│  cron 1 min     │     metrics.php     │  → central DB    │
└────────┬────────┘                     └──────────────────┘
         │
    /usr/local/pinguzo/
    ├── bin/pinguzo-agent.sh    # script principal bash
    ├── config/agent.conf       # agent_key, edge_url
    └── queue/                  # buffer offline
```

### Fichiers (chemins officiels)

| Chemin | Rôle |
|--------|------|
| `/usr/local/pinguzo/bin/pinguzo-agent.sh` | Collecte + envoi |
| `/usr/local/pinguzo/config/agent.conf` | Auth + endpoint |
| `/usr/local/pinguzo/queue/` | File d’attente si POST échoue |
| `/var/log/pinguzo/agent.log` | Logs |
| `/etc/cron.d/pinguzo` | `* * * * *` root |

### Cycle d’exécution (1 run)

1. Lire config (clé, edge)
2. Collecter métriques (voir liste ci-dessous)
3. Sérialiser JSON
4. POST HTTPS
5. Si échec → écrire dans `queue/`
6. Au run suivant → vider queue puis envoyer courant

### Métriques collectées ([doc](https://pinguzo.com/docs/install-agent))

- CPU Usage %, CPU Steal %
- Memory Usage %
- Disk Usage % (max partition)
- Load 1/5/15
- Uptime (secondes) — détection reboot
- Top 100 processus (CPU/RAM)
- Interfaces réseau RX/TX
- Partitions montées
- SMART (si smartctl)
- OS info + IP (premier check-in)

### Installateur

- Détecte edge le plus proche (latence healthz)
- Télécharge tarball versionnée (`1.0.1`)
- Cron metrics + cron update quotidien
- Flags : `--agent-key`, `--edge`, `--uninstall`, `--updates`

---

## Inspection sur le dédié Virtualizor (inspiration)

Commandes **lecture seule** — extrait réel (juillet 2026, serveur `datacenter`) :

```bash
ls -la /usr/local/pinguzo/
# bin/ config/ queue/

head -80 /usr/local/pinguzo/bin/pinguzo-agent.sh
# AGENT_VERSION="1.0.1"
# source agent.conf → PINGUZO_API_URL, PINGUZO_AGENT_KEY
# METRICS_ENDPOINT="/api/v1/metrics.php"
# QUEUE_FILE queue.dat, LOCK_FILE /var/run/pinguzo-agent.lock
# apply_lock() — exit si autre instance < 5 min
# UPDATE_API_URL api.pinguzo.com/updates.php

cat /etc/cron.d/pinguzo
# * * * * * root ... pinguzo-agent.sh  (chaque minute)
# 13 3 * * * root ... --updates

tail /var/log/pinguzo/agent.log
# queue retry: "Sent 1 queued metric(s) as a batch"
# daily info: OS + SMART + CPU static
```

### Patterns à reprendre pour ObiOra (Phase 3)

| Pattern Pinguzo | ObiOra cible |
|-----------------|--------------|
| Lock fichier PID + max age 5 min | Éviter double cron/systemd |
| `queue.dat` batch retry | `/var/lib/obiora/metrics-queue/` |
| Daily stamp (OS/SMART static) | Métadonnées serveur au 1er check-in |
| Update cron séparé | `update-panel.sh` existant |
| `json_unescape` sed | Validation JSON côté panel |

Commandes complètes :

```bash
# Structure
ls -la /usr/local/pinguzo/
ls -la /usr/local/pinguzo/bin/
head -80 /usr/local/pinguzo/bin/pinguzo-agent.sh

# Config (masquer la clé avant partage)
cat /usr/local/pinguzo/config/agent.conf | sed 's/agent_key=.*/agent_key=***REDACTED***/'

# Cron
cat /etc/cron.d/pinguzo

# Derniers logs
tail -50 /var/log/pinguzo/agent.log

# Queue offline
ls -la /usr/local/pinguzo/queue/ 2>/dev/null

# Taille / type du package
file /usr/local/pinguzo/bin/pinguzo-agent.sh
```

### Ce qu’on cherche à comprendre (sans copier)

| Question | Pourquoi |
|----------|----------|
| Quels fichiers `/proc` / commandes utilisés ? | Équivalent ObiOra Monitor Agent |
| Format JSON du payload ? | Schéma API ingest panel |
| Gestion erreurs réseau ? | Pattern queue |
| Détection reboot (uptime) ? | Règle alerte `Uptime < 300s` |
| SMART / steal / inodes ? | Parité métriques |

**Ne pas coller le script complet dans le repo ObiOra** — noter seulement les **idées** dans ce fichier ou un commentaire interne.

---

## Équivalent ObiOra proposé (Phase 3)

| Pinguzo | ObiOra Monitor Agent |
|---------|---------------------|
| `/usr/local/pinguzo/` | `/opt/obiora-panel/agent/monitor/` ou étendre agent slave |
| `agent.conf` + clé 64 hex | `agent_token` serveur existant dans panel |
| POST edge externe | `POST /api/v1/servers/{id}/monitor/metrics` |
| Cron 1 min | **Option A** : cron 1 min **Option B** : systemd timer (recommandé) |
| Queue locale | `storage/queue` côté agent ou `queue/` dans install dir |
| Install one-liner | `curl panel/install/monitor-agent.sh \| bash` |
| Uninstall | `obiora-monitor-agent.sh --uninstall` + modal panel |

### Réutilisation agents existants

Priorité d’intégration :

1. **Étendre collecte Crash Analyzer** (déjà riche, 5s) — agrégation 1 min pour graphs long terme
2. **Compléter agent slave** `/api/v1/metrics` — pinguzo-like pour slaves sans Crash
3. **Nouveau script léger** `obiora-monitor-agent.sh` — si besoin cron minimal type Pinguzo

---

## Différences voulues (mieux que Pinguzo)

- Pas de dépendance edge Softaculous
- Métriques + **Doctor score** + **Crash events** sur même fiche serveur
- Agent exécutable versionné Git (`chmod +x` — leçon v2.1.41)
- Télémétrie optionnelle CrashHunter en mode forensics

---

## Action utilisateur (optionnel)

Si vous exécutez les commandes d’inspection ci-dessus sur le dédié, copiez **uniquement** :

- Liste des métriques JSON (1 ligne anonymisée)
- Structure dossiers
- Extrait cron

→ On mettra à jour ce doc avec le schéma payload réel pour affiner Phase 3.
