# Référence Pinguzo — Analyse fonctionnelle & UX

Source : [Features](https://pinguzo.com/features/), [Docs](https://pinguzo.com/docs/), captures utilisateur (juillet 2026), log d’installation agent sur dédié Virtualizor.

---

## 1. Navigation produit

Sidebar (ordre observé sur screens) :

| Icône | Section | Rôle |
|-------|---------|------|
| Home | **Dashboard** | Vue d’ensemble |
| Globe | **Monitors** | Sites / services externes |
| Triangle | **Incidents** | Downtime & alertes déclenchées |
| Serveur | **Servers** | Machines Linux avec agent |
| Engrenage | **Alerts** | Politiques + Contacts |
| — | **Settings** | Profil, mot de passe, préférences |
| — | **Plans** | Quotas (SaaS) — **non requis ObiOra self-hosted** |

Bouton global **+ Add** (dropdown) : raccourci ajout serveur / moniteur.

---

## 2. Dashboard (screen `dashboard`)

### Cartes résumé (ligne 1)

| Carte | Contenu |
|-------|---------|
| **Servers** | Total, X online / Y offline |
| **Monitors** | Total, X up / Y down |
| **Open Incidents** | Nombre + « Needs attention » |
| **Plan** | Quota serveurs/moniteurs (SaaS) — **remplacer par quotas locaux ou illimité** |

### Listes condensées (ligne 2)

**Servers** : NAME, STATUS, LAST SEEN — lien « View all servers »

**Monitors** : NAME, STATUS, RESPONSE (ms), LAST CHECKED — lien « View all monitors »

### Incidents ouverts (ligne 3)

Table : RESOURCE, TYPE, MESSAGE, STARTED, STATUS

Exemple réel (screen) :
- Resource : Datacenter
- Type : High Disk (orange)
- Message : `Disk Usage is 100% (threshold: 90%)`
- Started : timestamp UTC
- Status : Open (rouge)

### Footer

`All times are UTC. The time now is …` — timezone affichage configurable dans Settings.

---

## 3. Monitors — Ajout (screen `add_site`)

Modal **Add Monitor** :

| Champ | Obligatoire | Exemple |
|-------|-------------|---------|
| Monitor Name | Oui | `deforest` |
| Type | Oui | `HTTPS` (dropdown) |
| URL | Oui (selon type) | `https://deforest.biz` |
| Check Interval | Non | `Every 5 minutes` (dropdown) |
| Tags | Non | `site` (chips, Enter ou virgule) |

**Types supportés** (confirmé utilisateur + doc) :

| Type | Cible | Notes UI |
|------|-------|----------|
| **HTTPS** | URL | « Checks HTTPS status + SSL certificate » |
| **HTTP** | URL | Statut HTTP sans SSL |
| **Ping** | Hostname/IP | ICMP ou équivalent depuis sonde |
| **Port** | Host + **port** | TCP connect |
| **Keyword** | URL + mot-clé | Présence/absence texte dans la page |
| **DNS** | Domaine | Résolution + latence |

Actions : Cancel / **Add Monitor**

---

## 4. Monitor Metrics (screen `monitor_metric_site`)

Page détail d’un moniteur `Monitors > deforest`.

### En-tête

- Badge **Up** (vert)
- Type : HTTPS
- Response : `117 ms`
- Last checked : timestamp

### Carte SSL

- Healthy / Expires in X days

### KPI (ligne)

| KPI | Exemple |
|-----|---------|
| Uptime | 100.00 % |
| Avg Response | 151 ms |
| Min / Max | 117 / 200 ms |
| TTFB | 56 ms |
| DNS Lookup | 13 ms |
| TCP Connect | 18 ms |

### Filtres temps

Boutons : `1h 6h 24h 3d 7d 30d 1M 3M 6M 1Y` + plage custom From/To + Apply

### Graphiques

1. **Response Time** — courbe avec légende Avg/Min/Max
2. **Status (Up/Down)** — timeline verte/rouge/gris (no data)

---

## 5. Servers — Ajout & installation (screens `add_server`, `add_server-2`)

### Modal Add Server

| Champ | Exemple |
|-------|---------|
| Server Name * | `Web Server 01` |
| Tags | chips optionnels |

→ **Add Server** génère une clé agent et ouvre modal **Install Agent**.

### Modal Install Agent

```
curl -fsSL https://api.pinguzo.com/files/install-agent.sh | \
  sudo bash -s -- --agent-key=XXXXXXXX
```

- Info : agent via **cron chaque minute**, online en 1–2 min
- Bouton **Done**

### Liste serveurs (colonnes observées)

| Colonne | Exemple |
|---------|---------|
| NAME | Datacenter |
| STATUS | Online / Installing |
| OS / KERNEL | AlmaLinux 10.2… |
| DISK HEALTH | Passed |
| LAST SEEN | timestamp |
| AGENT KEY | masqué + copie |
| ACTIONS | edit, metrics, delete… |

### Suppression serveur (screen `uninstall_server`)

Modal **Server Removed** avec commande :

```
/usr/local/pinguzo/bin/pinguzo-agent.sh --uninstall
```

Avertissement : stop agent, cron, fichiers.

---

## 6. Server Metrics (screens `server_metric`, `server_metric_disk`)

Route : `Servers > {name}` → onglets.

### Barre serveur

- Nom + **Online**
- Last seen, OS, kernel, **Agent v1.0.1**
- Badge disk health (Passed)

### Onglets

`Overview` | `CPU` | `Memory` | `Disk` | `Network` | `Processes` | `System`

### Filtres temps

Identiques aux Monitor Metrics.

### Overview (grille 2×2)

- CPU Usage % (area chart, Avg/Min/Max)
- Memory Usage %
- Disk Usage (root) %
- Load Average (1m / 5m / 15m)

### Disk (screen dédié)

- Graphique **Disk I/O (wait)** READ/WRITE
- Table **All Disk Partitions** : MOUNT, FILESYSTEM, TYPE, SIZE, USED, AVAILABLE, USAGE %, INODES USED, INODES %
- **Disk SMART Status** : device, Passed/Failed, température °C

---

## 7. Alerts (screens `alerte`, `edit_alert`)

### Onglets

**Alert Policies** | **Contacts**

### Politiques par défaut (9 observées)

| Nom | Condition résumée |
|-----|-------------------|
| High CPU Steal | CPU Steal > 10 % pendant 5 min |
| High CPU Usage | CPU > 90 % pendant 10 min |
| High Disk Usage | Disk > 90 % pendant 15 min |
| High Load Average | Load per Core > 2 pendant 10 min |
| High Memory Usage | Memory > 90 % pendant 10 min |
| Monitor Down | immédiat |
| No Data Received | 15 min sans données agent |
| Server Rebooted | Uptime < 300 s |
| SSL Certificate Expiring | Expiry < 14 jours |

Chaque ligne : toggle ON/OFF, texte `if … -> notify #287`, description aide, Edit/Delete.

### Modal Edit Alert

| Champ | Exemple |
|-------|---------|
| Alert Name | High CPU Steal |
| Metric | CPU Steal % |
| Condition | is greater than (>) |
| Value | 10 % |
| Alert after | 5 min (0 = immédiat) |
| Repeat alert after | 60 min (0 = jamais) |
| Apply to | All servers / monitors |
| Notify contacts | Default Contact (+ Add) |
| Description | texte aide |

---

## 8. Incidents (screen `incident`)

### Onglets

**Incidents** | **Notification Logs**

### Filtres

- Status : All / Open / Resolved
- Type : All / Servers / Monitors

### Table

| Colonne | Exemple |
|---------|---------|
| RESOURCE | Datacenter (icône serveur) |
| TRIGGER | High Disk |
| MESSAGE | Disk Usage is 100% (threshold: 90%) |
| WENT DOWN | 12 Jul 2024 19:00:05 |
| RECOVERED | — ou timestamp |
| DURATION | 9m (point rouge si open) |
| STATUS | Open |

---

## 9. Settings (screen `setting`)

### Profile

- Display Name, Email (read-only)

### Preferences

- **Timezone** dropdown (UTC, auto-detect, preview heure courante)
- **Language** (optionnel Phase 1+)

Note : timezone **affichage panel** ≠ timezone **système serveur** (les deux sont utiles).

---

## 10. Installation agent (log utilisateur dédié Virtualizor)

```
curl -fsSL https://api.pinguzo.com/files/install-agent.sh | \
  sudo bash -s -- --agent-key=ad317f8f...
```

Étapes observées :

1. Auto-détection edge (in, fi, uk, ca) → sélection **uk.pinguzo.com** (latence min)
2. Enregistrement edge central
3. Téléchargement package `download.php?version=1.0.1` → `/usr/local/pinguzo/`
4. Config `/usr/local/pinguzo/config/agent.conf`
5. Cron `/etc/cron.d/pinguzo` — **chaque minute**
6. Cron update quotidien (3:13 randomisé)
7. Test initial (peut échouer, retry cron)
8. Endpoint : `https://uk.pinguzo.com/api/v1/metrics.php`

**Points inspiration ObiOra** (pas de copie) :

- Install one-liner avec clé liée au serveur panel
- Edge auto vs panel unique (ObiOra = panel maître)
- Queue offline locale
- Cron simple vs systemd (ObiOra peut garder systemd + option cron léger)
- Uninstall propre avec commande affichée à la suppression

---

## 11. Équivalence ObiOra cible (résumé)

| Écran Pinguzo | Route ObiOra proposée |
|---------------|----------------------|
| Dashboard | `/monitoring` (refonte) |
| Monitors | `/monitoring/monitors` |
| Monitor detail | `/monitoring/monitors/{id}` |
| Servers | `/monitoring/servers` (ou réutiliser `/servers` enrichi) |
| Server metrics | `/monitoring/servers/{id}/metrics` |
| Incidents | `/monitoring/incidents` |
| Alerts | `/monitoring/alerts` |
| Settings timezone | `/settings` ou section Monitoring |
