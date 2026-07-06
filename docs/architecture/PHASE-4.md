# ObiOra Panel — Phase 4 : Services systemd (v1.3.0)

## Slave — installation serveur distant

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)
```

À la fin : **clé API** affichée dans le terminal.

Sur le maître : **Serveurs → Ajouter** → coller IP + clé API.

## Services systemd

Route : `/services`

| Action | Description |
|---|---|
| Start / Stop / Restart | Contrôle du service |
| Logs | 100 dernières lignes journalctl |
| Recherche | Filtre par nom |

Fonctionne sur le **serveur actif** (maître local ou slave via agent).

## Agent API (slave)

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/v1/ping` | GET | Santé + hostname, IP, OS |
| `/api/v1/metrics` | GET | Métriques système |
| `/api/v1/services` | GET | Liste services systemd |
| `/api/v1/services/action` | POST | start/stop/restart/reload/enable/disable |
| `/api/v1/services/logs` | GET | Logs journalctl |
