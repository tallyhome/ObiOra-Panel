# Phase 1 — Fondations & Dashboard Monitor

**Objectif** : poser le hub Monitoring unifié, le dashboard type Pinguzo, la gestion serveurs (liste + install agent), les préférences timezone affichage, sans encore les moniteurs externes ni les graphiques avancés.

**Durée estimée** : 1,5–2 semaines  
**Prérequis** : aucun (s’appuie sur `servers`, ping fleet, RBAC existants)

---

## 1. Livrables Phase 1

| # | Livrable | Priorité |
|---|----------|----------|
| 1.1 | Refonte route `/monitoring` — dashboard résumé | P0 |
| 1.2 | Navigation Monitor (sidebar ou sous-menu) | P0 |
| 1.3 | Page `/monitoring/servers` — liste enrichie | P0 |
| 1.4 | Modal Add Server + Install Agent (one-liner ObiOra) | P0 |
| 1.5 | Modal Server Removed + commande uninstall | P1 |
| 1.6 | Préférences timezone utilisateur (affichage panel) | P1 |
| 1.7 | Section incidents ouverts (lecture seule, données existantes) | P1 |
| 1.8 | Compteurs Monitors (placeholder « 0 » jusqu’à Phase 2) | P2 |

---

## 2. Dashboard (`/monitoring`)

### Wireframe fonctionnel (d’après screen Pinguzo)

```
┌──────────────────────────────────────────────────────────────┐
│  Dashboard                                    [ + Add ▼ ]    │
├────────────┬────────────┬────────────┬─────────────────────────┤
│ SERVERS    │ MONITORS   │ OPEN INC.  │ PLAN / INFO           │
│ 3          │ 0          │ 2          │ ObiOra self-hosted    │
│ 2 on / 1 off│ 0 up      │ attention  │                        │
├────────────┴────────────┴────────────┴─────────────────────────┤
│  Servers (mini table)          │  Monitors (mini table)       │
│  name | status | last seen    │  (vide ou « Phase 2 »)       │
├──────────────────────────────────────────────────────────────┤
│  Open Incidents                                              │
│  resource | type | message | started | status              │
└──────────────────────────────────────────────────────────────┘
│  All times are {user_tz}. Now: …                             │
└──────────────────────────────────────────────────────────────┘
```

### Sources de données Phase 1

| Carte | Source ObiOra actuelle |
|-------|------------------------|
| Servers online/offline | `Server` + `ServerPingService` / `status` |
| Monitors | Table vide Phase 2 → afficher `0` |
| Open incidents | Agréger `monitoring_alerts` non lues + incidents CrashHunter ouverts |
| Liste servers | `MonitoringFleetService` |
| Liste incidents | Nouveau `MonitoringIncidentService` (wrapper alertes) |

### Bouton + Add

Dropdown :

- **Add Server** → modal (existant enrichi ou nouveau)
- **Add Monitor** → désactivé ou « Bientôt Phase 2 »

---

## 3. Page Servers (`/monitoring/servers`)

### Colonnes (alignement Pinguzo)

| Colonne | Source |
|---------|--------|
| NAME | `servers.name` |
| STATUS | Online / Offline / Pending / Installing |
| OS / KERNEL | `os_name`, metadata kernel |
| DISK HEALTH | Doctor dernier rapport ou agent SMART |
| LAST SEEN | `last_seen_at` |
| AGENT KEY | `agent_token` masqué + copier |
| ACTIONS | Metrics (lien Phase 4), Edit, Delete, Reinstall |

### Modal Add Server

Champs :

- **Server Name** * (friendly name)
- **Tags** (JSON array ou table pivot `server_tags`)

Comportement :

1. Créer entrée `servers` (slave) + `server_nodes`
2. Générer `agent_token` si absent
3. Afficher modal **Install Agent** avec :

```bash
curl -fsSL https://{panel}/install/monitor-agent.sh | \
  sudo bash -s -- --panel-url={url} --server-id={id} --agent-token={token}
```

> Phase 1 : le script peut réutiliser l’install slave existant (`Slave/install.sh`) en attendant Phase 3.

Info box : « L’agent envoie les métriques chaque minute. Le serveur passe Online sous 1–2 min. »

### Modal Server Removed

À la suppression d’un serveur slave :

```bash
sudo /opt/obiora-panel/agent/bin/obiora-monitor-uninstall.sh --server-id={id}
```

(À créer Phase 1 minimal ou pointer vers doc uninstall slave)

---

## 4. Préférences timezone (screen Settings)

### Emplacement

`/settings` → section **Preferences** (ou profil utilisateur)

### Champs

| Champ | Comportement |
|-------|--------------|
| Timezone | Dropdown IANA (réutiliser `TimezoneCatalog`) |
| Auto-detect | Bouton JS `Intl.DateTimeFormat().resolvedOptions().timeZone` |
| Preview | « Current time in selected zone: … » |

Stockage : `users.timezone` ou `users.preferences` JSON.

**Impact** : toutes les dates Monitor, Incidents, Last Seen utilisent `Carbon` avec timezone utilisateur. Footer dashboard : `All times are {tz}`.

> Distinct de **timezone système serveur** (déjà prévu Doctor) — les deux coexistent.

---

## 5. Modèle de données Phase 1

### Nouvelles colonnes / tables (minimales)

```sql
-- users
ALTER TABLE users ADD timezone VARCHAR(64) NULL DEFAULT 'UTC';

-- servers (optionnel Phase 1)
ALTER TABLE servers ADD tags JSON NULL;

-- server_tags normalisé (optionnel si JSON suffit)
```

Pas de table `monitors` en Phase 1 (Phase 2).

### Service `MonitoringDashboardService`

Méthodes :

- `summary()` → counts servers/monitors/incidents
- `recentServers(limit: 5)`
- `recentMonitors(limit: 5)` → `[]` Phase 1
- `openIncidents(limit: 10)`

---

## 6. Permissions RBAC

| Action | Permission |
|--------|------------|
| Voir dashboard | `monitoring.view` |
| Ajouter serveur | `servers.manage` |
| Supprimer serveur | `servers.manage` |
| Préférences timezone | utilisateur connecté (son profil) |

---

## 7. UI / composants techniques

| Zone | Stack |
|------|-------|
| Dashboard | Livewire `MonitoringIndex` refonte OU Vue `MonitoringDashboard.vue` étendu |
| Modals | Livewire + Bootstrap modal (comme Doctor deploy) |
| Styles | Réutiliser `obiora-card`, badges status (vert/rouge/orange) |
| Temps réel | Conserver SSE/Reverb pour cartes Servers |

---

## 8. Critères d’acceptation Phase 1

- [ ] Dashboard affiche compteurs serveurs corrects (online/offline)
- [ ] Mini-table serveurs avec last seen en timezone utilisateur
- [ ] Add Server → commande install copiable one-liner
- [ ] Suppression serveur → modal uninstall avec commande
- [ ] Footer « All times are … » reflète timezone user
- [ ] Open Incidents affiche au moins alertes `server_offline` et `crash_analyzer` critiques
- [ ] Navigation vers Servers / (Monitors grisé) / Incidents / Alerts (Alerts peut être stub Phase 5)
- [ ] Aucune régression sur ping 30s et fleet existant

---

## 9. Tâches techniques (checklist dev)

### Backend

- [ ] Migration `users.timezone`
- [ ] `MonitoringDashboardService`
- [ ] `MonitoringIncidentService` (agrégation alertes)
- [ ] Route `GET /monitoring/servers`
- [ ] Endpoint API summary `GET /api/monitoring/summary`
- [ ] Settings Livewire : save timezone

### Frontend

- [ ] Layout Monitor sidebar (4–5 entrées)
- [ ] Cartes résumé dashboard
- [ ] Modals Add Server / Install Agent / Server Removed
- [ ] Page liste serveurs complète

### Ops

- [ ] Script public `routes/web.php` → `/install/monitor-agent.sh` (wrapper slave)
- [ ] Doc utilisateur lien depuis modal

### Tests

- [ ] Feature : dashboard loads with server counts
- [ ] Feature : timezone preference affects displayed date
- [ ] Unit : `MonitoringDashboardService::summary()`

---

## 10. Hors scope Phase 1

- Moniteurs HTTPS/Ping/Port (Phase 2)
- Graphiques CPU/Memory (Phase 4)
- Édition alert policies (Phase 5)
- Status page (Phase 6)
- Agent métriques dédié complet (Phase 3)

---

## 11. Prochaine étape

Une fois Phase 1 validée en prod sur le panel 239 :

→ Enchaîner [PHASE-2-moniteurs-externes.md](./PHASE-2-moniteurs-externes.md)
