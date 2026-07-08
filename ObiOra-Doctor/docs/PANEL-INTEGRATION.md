# Integration Obiora Panel SeedBox + Obiora Doctor

> **Documentation complète** : voir [ARCHITECTURE-SUITE.md](./ARCHITECTURE-SUITE.md)

## Architecture recommandee (votre idee)

```
┌─────────────────────────────┐
│   ObiOra Panel SeedBox      │  ← Dashboard web central
│   /monitoring               │     Ping, scores, alertes
│   API POST diagnostics      │
└──────────────┬──────────────┘
               │ HTTPS (agent_token)
    ┌──────────┼──────────┐
    ▼          ▼          ▼
┌────────┐ ┌────────┐ ┌────────┐
│ VPS 1  │ │ VPS 2  │ │Dedie   │  ← Agent minimal seulement
│ Agent  │ │ Agent  │ │Virtual.│     /opt/obiora-agent
└────────┘ └────────┘ └────────┘
```

**Un seul install lourd** : le panel sur le serveur maitre.
**Sur chaque VPS** : uniquement l'agent Obiora (~ Python + scripts).

## Installation agent sur un VPS

1. Creer le serveur dans le panel (`/servers/create`)
2. Copier le `agent_token` genere
3. Sur le VPS :

```bash
OBIORA_PANEL_URL=https://panel.example.com \
OBIORA_SERVER_ID=2 \
OBIORA_AGENT_TOKEN=le_token \
bash /opt/obiora-agent/install/install-agent.sh
```

4. L'agent scanne toutes les 5 min et pousse le rapport vers le panel.

## Configuration manuelle

Copier `config/agent-panel.json.example` vers `config/agent-panel.json` :

```json
{
  "panel_url": "https://panel.example.com",
  "server_id": 2,
  "agent_token": "..."
}
```

## Endpoints API panel

| Methode | Route | Auth |
|---------|-------|------|
| POST | `/api/v1/servers/{id}/diagnostics/reports` | Bearer agent_token |
| POST | `/api/v1/servers/{id}/diagnostics/heartbeat` | Bearer agent_token |

## UI panel

- `/monitoring` — vue fleet tous serveurs
- `/servers/{id}` — score Doctor + alertes critiques

## WHMCS — a quoi ca sert ?

**WHMCS** est un logiciel de **facturation et gestion clients** pour hebergeurs web.
Si installe sur le serveur, le module `whmcs` verifie sa presence et le cron.
Utile uniquement si vous hebergez WHMCS sur cette machine — sinon le module retourne "non detecte" sans erreur.
