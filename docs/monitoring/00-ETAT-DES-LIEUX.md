# État des lieux — ObiOra vs Pinguzo

## ObiOra aujourd’hui (déjà en production)

### Module Monitoring (`/monitoring`)

| Capacité | Détail | Fichiers clés |
|----------|--------|---------------|
| Ping flotte | ICMP puis fallback TCP:9100, intervalle ~30s | `ServerPingService`, `MonitorServersPingCommand` |
| Historique ping | ~24h (`server_ping_samples`) | `MonitoringFleetService` |
| Dashboard Vue | Table flotte, graphiques ping/score Doctor | `MonitoringDashboard.vue` |
| Alertes panel | `server_offline`, `diagnostic_critical`, `ssl_expiry`, `crash_analyzer`, `signature_invalid` | `MonitoringAlertService` |
| Temps réel | SSE `/api/monitoring/stream`, Reverb | `MonitoringStreamController` |
| Permissions | `monitoring.view` | RBAC |

### Agents & diagnostics (hors hub Monitor unifié)

| Composant | Rôle | Granularité |
|-----------|------|-------------|
| **Agent slave** | API HTTP :9100, métriques basiques, exécution distante | À la demande / dashboard |
| **Doctor** | Audit 25 modules, score 0–100, rapport signé | Périodique (timer) |
| **Crash Analyzer** | 23 collecteurs, détecteurs crash/reboot, push ~5s | Temps réel |
| **CrashHunter** | Forensics, incidents freeze, witness, snapshots | Temps réel + mode incident |

### Manques par rapport à Pinguzo (screens utilisateur)

| Fonction Pinguzo | ObiOra |
|------------------|--------|
| Dashboard résumé (serveurs / moniteurs / incidents / plan) | Partiel — pas de compteurs moniteurs ni incidents ouverts |
| **Moniteurs externes** (HTTPS, HTTP, Ping, Port, Keyword, DNS) | Absent |
| Liste **Monitors** avec type, intervalle, tags | Absent |
| **Server Metrics** onglets (Overview, CPU, Memory, Disk, Network, Processes, System) | Partiel — crash-analyzer charts, pas page serveur unifiée |
| **Monitor Metrics** (uptime %, TTFB, DNS, TCP, timeline up/down) | Absent |
| **Alert Policies** configurables (seuil + durée + repeat) | Partiel — règles codées en dur |
| **Contacts** notification (Slack, Discord, etc.) | Partiel — config `.env` Crash Analyzer |
| **Incidents** unifiés (resource, trigger, duration, resolved) | Partiel — alertes + CrashHunter incidents séparés |
| **Add Server** → modal install agent one-liner | Partiel — flux slaves / Doctor différent |
| **Uninstall agent** commande affichée à la suppression | Absent |
| **Préférences timezone** utilisateur panel | Absent (timezone serveur en cours dans Doctor) |
| **Status page** publique | Absent |
| **Import/Export** CSV/JSON | Absent |
| Rétention **60 jours** homogène | ~72h crash, ~24h ping |
| **CPU Steal %** | Absent |
| Table **partitions + inodes** | Partiel |
| **SMART** par disque avec température | Partiel (collecteur hardware) |

## Forces ObiOra (à conserver et mettre en avant)

- Panel hébergeur intégré (sites, BDD, Docker, backups, marketplace)
- Diagnostic Doctor (score, modules, SSL, RAID, MySQL…)
- Détection crash / reboot / freeze (Crash Analyzer + CrashHunter)
- Multi-serveurs maître + slaves sans dépendance cloud tiers
- Déploiement agents one-liner déjà en place (Doctor/Crash)

## Stratégie

**Ne pas tout réécrire** — construire une couche **ObiOra Monitor** au-dessus de l’existant :

```
┌─────────────────────────────────────────────────────────┐
│  ObiOra Monitor (nouveau hub UI + moniteurs + alertes) │
├─────────────────────────────────────────────────────────┤
│  Agent Monitor (Phase 3) │  Sondes externes (Phase 2)  │
├─────────────────────────────────────────────────────────┤
│  Slave agent │ Crash Analyzer │ Doctor │ CrashHunter   │
└─────────────────────────────────────────────────────────┘
```
