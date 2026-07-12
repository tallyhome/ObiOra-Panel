# Phase 3 — Agent métriques serveur unifié

**Objectif** : parité collecte **Pinguzo agent** (cron 1 min, push HTTPS sortant, queue offline) intégrée au panel ObiOra, sans dépendance edge externe.

**Durée estimée** : 2–3 semaines  
**Prérequis** : Phase 1 (install one-liner, liste serveurs)

Référence inspiration : [02-AGENT-INSPIRATION.md](./02-AGENT-INSPIRATION.md)

---

## 1. Choix d’architecture

### Option retenue (recommandée)

**Étendre l’écosystème existant** plutôt qu’un agent isolé :

| Serveur | Agent principal | Fréquence |
|---------|-----------------|-----------|
| Maître (panel) | Script `obiora-metrics-push.sh` + timer systemd 1 min | 60s |
| Slave | Même script ou enrichissement `agent/public/index.php` push | 60s |
| Avec Crash Analyzer | Agrégation 1 min depuis collecteurs 5s (évite double charge) | 60s + 5s |

### Install one-liner (panel)

```bash
curl -fsSL https://{panel}/install/monitor-agent.sh | \
  sudo bash -s -- \
    --panel-url=https://panel.example \
    --server-id=2 \
    --agent-token=...
```

Étapes installateur ObiOra :

1. Vérifier Linux + curl + bash
2. Copier scripts dans `/opt/obiora-panel/agent/monitor/`
3. Écrire `/etc/obiora/monitor-agent.env`
4. `systemctl enable --now obiora-metrics.timer` (ou cron `/etc/cron.d/obiora-metrics`)
5. Test POST immédiat → log `/var/log/obiora/metrics-agent.log`
6. `chmod +x` tous binaires (leçon v2.1.41)

---

## 2. Métriques à collecter (parité Pinguzo)

| Métrique | Source Linux | Priorité |
|----------|--------------|----------|
| CPU Usage % | `/proc/stat` | P0 |
| CPU Steal % | `/proc/stat` (st) | P0 |
| Memory Usage % | `/proc/meminfo` | P0 |
| Swap Usage % | `/proc/meminfo` | P1 |
| Disk Usage % max partition | `df` | P0 |
| Disk partitions détail | `df` + inodes | P1 |
| Load 1/5/15 | `/proc/loadavg` | P0 |
| Uptime seconds | `/proc/uptime` | P0 |
| Network RX/TX per iface | `/proc/net/dev` | P1 |
| Top processes (100) | `ps` tri CPU/RAM | P1 |
| SMART health + temp | `smartctl` | P1 |
| OS + kernel + arch | `uname`, `/etc/os-release` | P0 |
| Primary IP | `hostname -I` | P0 |

Payload JSON versionné `schema_version: 1`.

---

## 3. API ingest panel

```
POST /api/v1/servers/{server}/monitor/metrics
Authorization: Bearer {agent_token}
Content-Type: application/json
```

Réponse :

```json
{ "ok": true, "next_push_seconds": 60 }
```

### Table `server_metric_samples`

| Colonne | Type |
|---------|------|
| server_id | FK |
| sampled_at | timestamp |
| cpu_percent | decimal |
| cpu_steal_percent | decimal |
| memory_percent | decimal |
| disk_percent | decimal |
| load_1 / load_5 / load_15 | decimal |
| uptime_seconds | bigint |
| payload | json (partitions, processes, smart, network) |

Rétention : 60 jours (job prune nightly).

---

## 4. Queue offline (inspiration Pinguzo)

Répertoire agent : `/var/lib/obiora/metrics-queue/`

- Si POST échoue → écrire `{timestamp}.json`
- Run suivant : envoyer queue FIFO puis courant
- Limite queue : 1440 fichiers (24h à 1/min)

---

## 5. Statut serveur Online / No data

| État | Condition |
|------|-----------|
| **Online** | Dernier sample < 3 min |
| **Degraded** | 3–15 min sans data |
| **Offline** | > 15 min (aligné alerte Pinguzo) |

Met à jour `servers.last_seen_at` et `status`.

---

## 6. Uninstall

```bash
sudo /opt/obiora-panel/agent/monitor/obiora-metrics-uninstall.sh
```

- Stop timer/cron
- Supprime unit systemd
- Option : garder logs

Modal panel à la suppression serveur (Phase 1).

---

## 7. Critères d’acceptation

- [ ] Install one-liner fonctionne sur AlmaLinux 10 (dédié Virtualizor)
- [ ] Métriques visibles en base < 2 min
- [ ] CPU steal collecté sur VM
- [ ] Queue offline rejoue après coupure réseau simulée
- [ ] `chmod +x` garanti post git pull (update-panel.sh)
- [ ] Pas de port entrant requis sur slave

---

## 8. Relation avec Crash Analyzer

| Cas | Comportement |
|-----|--------------|
| Crash Analyzer installé | Metrics 1 min = rollup CA ; pas second daemon |
| Slave minimal | Script bash léger seul |
| Maître panel | Timer local + sudoers |

Éviter 3 agents redondants — documenter matrice dans README monitor.
