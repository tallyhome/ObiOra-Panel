# Phase 4 — Graphiques & pages métriques

**Objectif** : reproduire les écrans **Server Metrics** et **Monitor Metrics** Pinguzo (onglets, filtres temps, graphiques ApexCharts).

**Durée estimée** : 2–3 semaines  
**Prérequis** : Phase 2 (monitors), Phase 3 (server metric samples)

---

## 1. Server Metrics (`/monitoring/servers/{id}/metrics`)

### En-tête serveur (barre info)

Reprendre le screen `server_metric` :

| Élément | Source |
|---------|--------|
| Nom + badge Online/Offline | `servers` |
| Last seen | `last_seen_at` |
| OS / kernel | metadata agent |
| Agent version | `metadata.monitor_agent_version` |
| Disk health SMART | dernier payload SMART |

### Onglets

| Onglet | Contenu Phase 4 |
|--------|-----------------|
| **Overview** | CPU, Memory, Disk root %, Load 1/5/15 (grille 2×2) |
| **CPU** | Usage % + Steal % |
| **Memory** | RAM % + Swap % |
| **Disk** | I/O wait, table partitions, SMART (screen `server_metric_disk`) |
| **Network** | RX/TX par interface |
| **Processes** | Top 100 snapshot table |
| **System** | Uptime, reboot history, IP list, SSH sessions count |

### Filtres temps (commun)

Boutons preset :

`1h` `6h` `24h` `3d` `7d` `30d` `1M` `3M` `6M` `1Y`

+ Quick select dropdown  
+ Custom From/To datetime + **Apply**

API :

```
GET /api/monitoring/servers/{id}/metrics?from=&to=&resolution=auto
```

`resolution` auto : 1 min (< 24h), 5 min (< 7d), 1 h (> 7d).

### Graphiques (ApexCharts)

Chaque chart header :

- Titre
- Légende
- **Avg / Min / Max** (coin supérieur droit — comme Pinguzo)

Types :

- Area chart CPU/Memory
- Multi-line Load (1m/5m/15m)
- Line Disk usage
- Bar ou line Disk I/O READ/WRITE

### Table partitions (onglet Disk)

Colonnes screen utilisateur :

MOUNT | FILESYSTEM | TYPE | SIZE | USED | AVAILABLE | USAGE % (barre) | INODES USED | INODES %

Source : dernier payload `partitions[]` dans sample.

### SMART section

Par device `/dev/sdX` :

- Status Passed/Failed/N/A
- Temperature °C

---

## 2. Monitor Metrics (`/monitoring/monitors/{id}/metrics`)

### En-tête

- Badge Up/Down
- Type (HTTPS…)
- Response ms
- Last checked

### Carte SSL (HTTPS uniquement)

- Healthy / Warning / Expired
- Expires in X days
- Lien vers rapport Doctor SSL (Phase 7)

### KPI ligne

| KPI | Calcul |
|-----|--------|
| Uptime % | checks up / total sur période |
| Avg Response | moyenne `response_ms` |
| Min / Max | min/max période |
| TTFB | moyenne `metrics.ttfb_ms` |
| DNS Lookup | moyenne `metrics.dns_ms` |
| TCP Connect | moyenne `metrics.tcp_connect_ms` |

### Graphiques

1. **Response Time** — courbe + bande min/max
2. **Status timeline** — vert up / rouge down / gris no data (comme screen)

Implémentation timeline : ApexCharts rangeBar ou custom SVG segments.

---

## 3. Performance & stockage

- Index BDD `(server_id, sampled_at)` et `(monitor_id, checked_at)`
- Cache Redis agrégats 1h/24h pour dashboard rapide
- Pagination API : max 2000 points par requête → downsample côté serveur

---

## 4. Critères d’acceptation

- [ ] Overview 4 charts chargent < 2s pour 24h de data
- [ ] Filtres temps changent toutes les vues
- [ ] Onglet Disk affiche partitions + SMART comme screen
- [ ] Monitor metrics affiche uptime % et timeline status
- [ ] Timestamps respectent timezone utilisateur (Phase 1)
- [ ] Mobile : tables scroll horizontal

---

## 5. Composants frontend

| Composant | Tech |
|-----------|------|
| `ServerMetricsPage.vue` ou Livewire | ApexCharts |
| `MonitorMetricsPage.vue` | ApexCharts |
| `TimeRangePicker` | Réutilisable |
| `MetricStatCard` | KPI box |
| `PartitionTable` | Bootstrap table + progress bars |

---

## 6. Hors scope Phase 4

- Export PNG graphiques
- Comparaison multi-serveurs overlay (Phase 7)
