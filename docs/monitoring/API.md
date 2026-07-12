# API Monitoring ObiOra (Phase 6)

Base URL session (panel connecté) : `/api/v1/monitoring`  
Base URL token Sanctum : `/api/v1/monitoring` (header `Authorization: Bearer {token}`)

## Serveurs

- `GET /servers` — liste (sans `agent_token`)
- `GET /servers/{id}/metrics?preset=24h` — métriques agrégées

## Moniteurs

- `GET /monitors`
- `POST /monitors` — création (`monitoring.manage`)
- `GET /monitors/{id}/checks`
- `GET /monitors/export/json`
- `POST /monitors/import/json` — body `{ "monitors": [...] }`

## Incidents & alertes

- `GET /incidents`
- `GET /alert-policies`

## Compteur de visites (sites)

- Pixel public : `GET /track/{track_token}.gif`
- Script embed : `GET /track/{track_token}.js`

Le `track_token` est généré automatiquement pour les moniteurs HTTP/HTTPS/Keyword.

## Status page publique

- `GET /status` — sans authentification (config : `/monitoring/settings/status-page`)
