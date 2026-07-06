# ObiOra Panel — Phase 3 : Dashboard & Auth (v1.2.0)

## Routes

| Route | Description |
|---|---|
| `/setup` | Configuration initiale |
| `/login` | Connexion |
| `/dashboard` | Tableau de bord |
| `/servers` | Liste des serveurs |
| `/servers/create` | Ajouter un serveur |
| `/servers/{id}` | Détail serveur |

## Ajouter un serveur distant (v1.3.0+)

1. Sur le **slave** :

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)
```

2. Copiez la **clé API** affichée à la fin.
3. Sur le **maître** : Serveurs → Ajouter → IP + clé API.
4. **Ping** pour valider.

Voir [Slave/README.md](../../Slave/README.md) et [PHASE-4.md](PHASE-4.md).
