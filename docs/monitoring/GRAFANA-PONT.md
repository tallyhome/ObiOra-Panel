# Pont Grafana — ObiOra Monitor+

Guide pour brancher [Grafana OSS](https://grafana.com/) ou Prometheus à côté d'ObiOra **sans remplacer** le NOC intégré (Doctor, Crash, maintenance, contacts).

---

## 1. Activer l'export ObiOra

Dans `/opt/obiora-panel/.env` :

```env
OBIORA_PROMETHEUS_ENABLED=true
OBIORA_PROMETHEUS_TOKEN=changez-moi-en-production-min-32-chars
```

Puis :

```bash
sudo -u obiora php artisan config:clear
```

Test :

```bash
curl -sS -H "Authorization: Bearer changez-moi-en-production-min-32-chars" \
  https://panel.example.com/metrics | head
```

Réponse attendue (extrait) :

```
# HELP obiora_server_up Serveur joignable (1=online)
# TYPE obiora_server_up gauge
obiora_server_up{resource="server",id="2",name="Node-1"} 1
obiora_server_cpu_percent{resource="server",id="2",name="Node-1"} 12.5
```

---

## 2. Métriques exposées

| Métrique | Description | Labels |
|----------|-------------|--------|
| `obiora_build_info` | Version panel | `version` |
| `obiora_server_up` | Serveur online | `id`, `name`, `resource=server` |
| `obiora_server_cpu_percent` | CPU % | idem |
| `obiora_server_memory_percent` | RAM % | idem |
| `obiora_server_disk_percent` | Disque % | idem |
| `obiora_server_cpu_steal_percent` | CPU steal (VM) | idem |
| `obiora_doctor_score` | Score Doctor 0–100 | idem |
| `obiora_monitor_up` | Moniteur externe OK | `id`, `name`, `resource=monitor` |
| `obiora_monitor_response_ms` | Latence dernière sonde | idem |

Aucun token agent, mot de passe ou clé API n'est exposé dans les labels.

---

## 3. Prometheus (scrape direct)

`prometheus.yml` :

```yaml
scrape_configs:
  - job_name: obiora-panel
    scrape_interval: 60s
    metrics_path: /metrics
    scheme: https
    bearer_token: "changez-moi-en-production-min-32-chars"
    static_configs:
      - targets: ['panel.example.com']
```

Recharger Prometheus : `kill -HUP $(pidof prometheus)` ou restart du conteneur.

---

## 4. Grafana OSS (Docker)

```bash
docker run -d --name grafana -p 3000:3000 grafana/grafana
```

1. Ouvrir http://localhost:3000 (admin / admin)
2. **Connections → Data sources → Add Prometheus**
3. URL : `https://panel.example.com/metrics` **ou** URL Prometheus intermédiaire
4. Auth : **Bearer token** = `OBIORA_PROMETHEUS_TOKEN`
5. **Save & test**

> Si Grafana scrape via Prometheus intermédiaire, configurez Prometheus (§3) et pointez Grafana vers `http://prometheus:9090`.

---

## 5. Dashboard minimal

Créer un panel **Time series** avec requête :

```promql
obiora_server_cpu_percent
```

Variables Grafana suggérées :

- `$server` : `label_values(obiora_server_up, name)`

Panels utiles :

- CPU / RAM / disque par serveur
- `obiora_monitor_up` (uptime synthétique)
- `obiora_doctor_score` (santé diagnostic)

Templates communautaires : [grafana.com/grafana/dashboards](https://grafana.com/grafana/dashboards/) — adapter les noms de métriques au préfixe `obiora_*`.

---

## 6. Architecture recommandée

```
┌─────────────┐     scrape /metrics      ┌────────────┐
│ ObiOra Panel│ ◄─────────────────────── │ Prometheus │
│ Monitor+    │                          └─────┬──────┘
│ Doctor/Crash│                                │
└─────────────┘                                ▼
                                        ┌────────────┐
                                        │  Grafana   │
                                        │ (viz only) │
                                        └────────────┘
```

- **ObiOra** : alertes, maintenance, contacts, actions hébergeur
- **Grafana** : dashboards avancés, corrélation long terme, PromQL

---

## 7. Dépannage

| Problème | Solution |
|----------|----------|
| HTTP 404 sur `/metrics` | `OBIORA_PROMETHEUS_ENABLED=true` + `config:clear` |
| HTTP 401 | Vérifier Bearer token identique à `.env` |
| Métriques vides | Agents métriques actifs ? `server_metric_samples` peuplé ? |
| Grafana « no data » | Scrape interval ≥ 60s ; vérifier TLS / certificat panel |

---

*ObiOra v4.0.0 — voir aussi [ROADMAP-MONITOR-GRAFANA-DEDIE.md](./ROADMAP-MONITOR-GRAFANA-DEDIE.md)*
