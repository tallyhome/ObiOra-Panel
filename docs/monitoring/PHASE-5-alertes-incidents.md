# Phase 5 — Alertes, contacts & incidents

**Objectif** : parité Pinguzo **Alerts** + **Incidents** — politiques configurables, contacts multi-canaux, lifecycle incident unifié.

**Durée estimée** : 2–3 semaines  
**Prérequis** : Phase 2–3 (monitors + métriques serveur)

---

## 1. Alert Policies (screen `alerte`, `edit_alert`)

### Routes

- `/monitoring/alerts` — onglet **Alert Policies**
- `/monitoring/alerts/contacts` — onglet **Contacts**

### Modèle `alert_policies`

| Colonne | Type | Exemple |
|---------|------|---------|
| name | string | High Disk Usage |
| metric | string | disk_usage_percent |
| operator | enum(gt,lt,gte,lte,eq) | gt |
| value | decimal | 90 |
| value_unit | string | % |
| duration_minutes | int | 15 (0 = immédiat) |
| repeat_minutes | int | 60 (0 = pas de repeat) |
| apply_to | enum(all,servers,monitors,server_tag) | all |
| apply_target_ids | json nullable | [1,2] |
| notify_contact_ids | json | [1] |
| description | text | aide utilisateur |
| is_enabled | bool | true |

### Métriques supportées (parité Pinguzo)

**Serveur** :

| Metric key | Description |
|------------|-------------|
| `cpu_usage_percent` | CPU global |
| `cpu_steal_percent` | CPU steal (VM) |
| `memory_usage_percent` | RAM |
| `disk_usage_percent` | Max partition |
| `load_per_core` | load_1 / cores |
| `uptime_seconds` | reboot si < 300 |
| `agent_no_data_minutes` | pas de sample |

**Moniteur** :

| Metric key | Description |
|------------|-------------|
| `monitor_status` | down |
| `ssl_expiry_days` | jours restants SSL |

### Politiques par défaut (seed)

Reprendre les 9 politiques Pinguzo (voir [01-REFERENCE-PINGUZO.md](./01-REFERENCE-PINGUZO.md)) avec contact « Default ».

### Moteur d’évaluation

Job `obiora:evaluate-alert-policies` — toutes les 1 min :

1. Charger politiques actives
2. Pour chaque serveur/moniteur applicable → lire dernière métrique
3. Vérifier condition + durée persistante (fenêtre glissante)
4. Créer ou mettre à jour **incident**
5. Notifier si nouveau ou repeat écoulé

---

## 2. Contacts (notifications)

### Modèle `alert_contacts`

| Colonne | Type |
|---------|------|
| name | string |
| email | string nullable |
| slack_webhook | string nullable |
| discord_webhook | string nullable |
| telegram_bot_token | string nullable |
| telegram_chat_id | string nullable |
| webhook_url | string nullable |
| is_default | bool |

UI : formulaire par canal (réutiliser config Crash Analyzer comme défaut).

### Notification Logs (onglet Incidents)

Table `notification_logs` :

| Colonne | Type |
|---------|------|
| incident_id | FK nullable |
| contact_id | FK |
| channel | email/slack/… |
| status | sent/failed |
| response | text |
| sent_at | timestamp |

---

## 3. Incidents (screen `incident`)

### Modèle `monitoring_incidents`

| Colonne | Type |
|---------|------|
| id | bigint |
| resource_type | server / monitor |
| resource_id | bigint |
| trigger | string (High Disk, Monitor Down…) |
| message | text |
| policy_id | FK nullable |
| went_down_at | timestamp |
| recovered_at | timestamp nullable |
| status | open / resolved |
| metadata | json |

### UI

Filtres :

- Status : All / Open / Resolved
- Type : All / Servers / Monitors

Colonnes :

RESOURCE | TRIGGER | MESSAGE | WENT DOWN | RECOVERED | DURATION | STATUS

**Duration** : calcul live si open (`now - went_down_at`).

Actions :

- Marquer résolu manuellement
- Notes / annotations (post-mortem) — champ `metadata.notes[]`

### Migration données existantes

- `monitoring_alerts` → alimenter incidents ou coexister (wrapper)
- CrashHunter incidents → lien optionnel `metadata.crash_hunter_incident_id`

---

## 4. Intégration dashboard Phase 1

Carte **Open Incidents** et table utilisent `monitoring_incidents` où `status=open`.

---

## 5. Critères d’acceptation

- [ ] CRUD politiques avec modal Edit comme screen
- [ ] Toggle enable/disable politique
- [ ] High Disk 90% 15 min crée incident (test sur dédié 100% disk screen)
- [ ] Monitor Down crée incident immédiat
- [ ] Server Reboot (uptime < 300) déclenche après reboot
- [ ] Notification email + au moins 1 canal chat
- [ ] Repeat alert 60 min fonctionne
- [ ] Incident passe Resolved quand métrique OK
- [ ] Notification Logs enregistre envois

---

## 6. Permissions

| Action | Permission |
|--------|------------|
| Voir incidents | `monitoring.view` |
| Gérer politiques/contacts | `monitoring.manage` (nouveau) ou `servers.manage` |

---

## 7. Tests

- Unit : `AlertPolicyEvaluator` (duration, repeat, recovery)
- Feature : disk 100% → incident open
- Feature : monitor down → notify contact mock
