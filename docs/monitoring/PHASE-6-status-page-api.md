# Phase 6 — Status page, API & import/export

**Objectif** : fonctionnalités « produit complet » Pinguzo — status page publique, API REST, import/export CSV/JSON.

**Durée estimée** : 2 semaines  
**Prérequis** : Phases 2–5

---

## 1. Status page publique

### Routes

- `/status` — page publique (sans auth)
- `/status/{slug}` — status page personnalisée (optionnel)

### Contenu

| Section | Source |
|---------|--------|
| Global status | All operational / Partial outage / Major outage |
| Servers | Nom + Online/Offline (sans IP sensible) |
| Monitors | Nom + Up/Down + uptime 30j % |
| Incidents récents | 7 derniers jours resolved + open |
| Historique | Timeline 90 jours (bande verte/rouge) |

### Config admin

`/monitoring/settings/status-page` :

- Activer/désactiver
- Slug custom
- Logo / titre
- Moniteurs/serveurs visibles (checkbox)
- Branding ObiOra minimal

### Sécurité

- Pas de tokens, IPs internes, agent keys
- Rate limit public
- Option `noindex` meta

---

## 2. API REST (authentifiée)

Token API panel (Sanctum ou clé installation) — parité Pinguzo Business plan.

### Endpoints

```
GET    /api/v1/monitoring/servers
POST   /api/v1/monitoring/servers
GET    /api/v1/monitoring/servers/{id}/metrics

GET    /api/v1/monitoring/monitors
POST   /api/v1/monitoring/monitors
GET    /api/v1/monitoring/monitors/{id}/checks

GET    /api/v1/monitoring/incidents
GET    /api/v1/monitoring/alert-policies
```

Documentation OpenAPI dans `docs/monitoring/API.md`.

---

## 3. Import / Export

### Monitors

**Export JSON** :

```json
{
  "version": 1,
  "monitors": [
    {
      "name": "deforest",
      "type": "https",
      "target": "https://deforest.biz",
      "interval_seconds": 300,
      "tags": ["site"]
    }
  ]
}
```

**Export CSV** : name,type,target,port,keyword,interval,tags

**Import** : validation + création bulk, skip doublons par name.

### Servers

Export métadonnées (sans `agent_token` complet) — import = création + nouvelle clé.

Bouton header **Import / Export** sur pages Monitors et Servers (screen Pinguzo).

---

## 4. Rétention & quotas

Config `config/monitoring.php` :

```php
'retention_days' => 60,
'max_monitors' => null, // illimité self-hosted
'max_servers' => null,
```

Job prune aligné 60 jours (Pinguzo paid).

---

## 5. Multi-sonde (optionnel avancé)

Pinguzo utilise des **edge servers** multi-région pour vérifier les moniteurs.

Phase 6 option **6b** :

- Définir `probe_nodes` (slaves autorisés à sonder)
- Monitor check depuis 2+ nodes → quorum up/down
- Réduit faux positifs réseau panel

Non bloquant pour MVP.

---

## 6. Critères d’acceptation

- [ ] `/status` accessible sans login
- [ ] API CRUD monitors documentée
- [ ] Export/import JSON 10 moniteurs OK
- [ ] Rétention 60j appliquée
- [ ] Aucun secret dans export

---

## 7. Hors scope

- Facturation / plans SaaS (screen Plans Pinguzo) — ObiOra self-hosted sans quota payant
- Edge auto-discovery Softaculous
