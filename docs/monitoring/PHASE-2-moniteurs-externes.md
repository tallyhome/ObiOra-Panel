# Phase 2 — Moniteurs externes (Sites & services)

**Objectif** : équivalent Pinguzo **Monitors** — surveiller URLs et services depuis le panel (sondes), avec types HTTPS, HTTP, Ping, Port, Keyword, DNS.

**Durée estimée** : 2–3 semaines  
**Prérequis** : Phase 1 (dashboard, navigation)

---

## 1. Types de moniteurs

| Type | Paramètres | Vérifications |
|------|------------|---------------|
| **HTTPS** | URL, intervalle, tags | HTTP status, latence, SSL expiry, TTFB |
| **HTTP** | URL | Status, latence (sans vérif SSL stricte) |
| **Ping** | Host/IP | ICMP ou TCP ping fallback |
| **Port** | Host, **port** | TCP connect + temps |
| **Keyword** | URL, keyword, présence/absence | Corps réponse contient texte |
| **DNS** | Hostname, record type (A/AAAA/CNAME) | Résolution + latence |

### Intervalles (dropdown UI)

| Label | Secondes |
|-------|----------|
| Every 1 minute | 60 |
| Every 5 minutes | 300 |
| Every 10 minutes | 600 |
| Every 30 minutes | 1800 |

Plan ObiOra self-hosted : tous intervalles disponibles (pas de quota SaaS).

---

## 2. UI — Liste & ajout

### Route

- `/monitoring/monitors`
- Modal **Add Monitor** (screen utilisateur)

### Liste colonnes

| Colonne | Contenu |
|---------|---------|
| NAME | Nom friendly |
| TYPE | Badge HTTPS/HTTP/… |
| TARGET | URL ou host:port |
| STATUS | Up / Down / Unknown |
| RESPONSE | Dernier ms |
| LAST CHECKED | Timestamp user TZ |
| ACTIONS | Edit, Pause, Delete, Metrics |

### Import / Export

Reporté Phase 6 — stub bouton « Import / Export » désactivé en Phase 2.

---

## 3. Moteur de sondes

### Architecture

```
┌─────────────────┐
│  Laravel Scheduler │  obiora:run-monitors (every minute)
└────────┬────────┘
         │
┌────────▼────────┐     ┌──────────────────┐
│ MonitorRunner   │────►│ MonitorCheckJob  │ (queue, 1 job/monitor)
└─────────────────┘     └────────┬─────────┘
                                 │
                    HTTP client / fsockopen / dns_get_record
                                 │
                    ┌────────────▼────────────┐
                    │ monitor_checks table    │
                    │ monitoring_incidents    │
                    └─────────────────────────┘
```

### Où exécuter les sondes

| Option | Avantage | Inconvénient |
|--------|----------|--------------|
| **Panel maître** (Phase 2 initial) | Simple | Point unique, pas multi-région |
| Workers slaves (Phase 6+) | Distribué | Plus complexe |

**Phase 2** : sondes depuis le **serveur panel** uniquement.

### Métriques par check

```json
{
  "status": "up|down",
  "response_ms": 151,
  "ttfb_ms": 56,
  "dns_ms": 13,
  "tcp_connect_ms": 18,
  "http_code": 200,
  "ssl_days_remaining": 57,
  "error": null,
  "keyword_found": true
}
```

---

## 4. Modèle de données

### `monitors`

| Colonne | Type |
|---------|------|
| id | bigint |
| name | string |
| type | enum(https,http,ping,port,keyword,dns) |
| target | string (URL ou host) |
| port | int nullable |
| keyword | string nullable |
| keyword_present | bool default true |
| interval_seconds | int default 300 |
| tags | json nullable |
| is_active | bool default true |
| last_status | string nullable |
| last_checked_at | timestamp nullable |
| last_response_ms | int nullable |
| created_at / updated_at | timestamps |

### `monitor_checks` (historique)

| Colonne | Type |
|---------|------|
| id | bigint |
| monitor_id | FK |
| status | up/down |
| response_ms | int |
| metrics | json (ttfb, dns, ssl, …) |
| checked_at | timestamp |

Index : `(monitor_id, checked_at)`.

Rétention Phase 2 : 30 jours (configurable).

---

## 5. Page Monitor Metrics (aperçu Phase 2 minimal)

Route : `/monitoring/monitors/{id}`

Phase 2 livre :

- En-tête : status, type, response, last checked
- KPI : uptime % (calculé sur période), avg/min/max response
- Table derniers checks

Graphiques complets → **Phase 4**.

---

## 6. Intégration dashboard Phase 1

- Carte **Monitors** : compteurs réels up/down
- Mini-table monitors sur dashboard
- Incidents type `monitor_down` (Phase 5)

---

## 7. Critères d’acceptation

- [ ] CRUD moniteurs tous types
- [ ] Check HTTPS sur URL réelle avec latence stockée
- [ ] Check Port sur host:port
- [ ] Keyword détecte présence texte
- [ ] DNS résout et mesure latence
- [ ] Scheduler exécute checks selon intervalle
- [ ] Dashboard monitors à jour
- [ ] Pause/reprise moniteur sans suppression

---

## 8. Tests

- Unit : `HttpsMonitorProbe`, `PortMonitorProbe`, …
- Feature : create monitor → run command → check recorded
- Feature : keyword monitor up/down sur fixture HTML
