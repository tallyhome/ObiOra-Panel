# ObiOra Panel — Phase 3 : Dashboard & Auth (v1.2.0)

## Fonctionnalités

- **Setup wizard** : `/setup` — création du premier compte super-admin
- **Authentification** : `/login` — Livewire + session Laravel
- **Dashboard** : `/dashboard` — métriques CPU, RAM, disque, uptime + graphiques ApexCharts
- **Multi-serveurs** :
  - Sélecteur de serveur dans la barre de navigation
  - Liste / ajout / détail des serveurs (`/servers`)
  - Agent HTTP sur port 9100 pour serveurs distants
  - Serveur maître (local) + serveurs distants via agent

## Flux premier démarrage

1. Ouvrir `http://IP/` → redirection `/setup`
2. Créer le compte administrateur
3. Accès au dashboard

## Ajouter un serveur distant

1. Sur le VPS distant : installer PHP 8.3+, cloner le repo ou copier `agent/`
2. Panel → Serveurs → Ajouter
3. Copier le **token agent** affiché sur la fiche serveur
4. Sur le VPS distant :

```bash
cat > /opt/obiora-panel/agent/config/agent.json <<EOF
{"host":"0.0.0.0","port":9100,"token":"VOTRE_TOKEN"}
EOF
bash /opt/obiora-panel/agent/bin/obiOra-agent start
```

5. Ouvrir le port 9100 (ou tunnel SSH) depuis le panel maître
6. Cliquer **Ping** — statut passe à `online`

## Routes

| Route | Description |
|---|---|
| `/setup` | Configuration initiale |
| `/login` | Connexion |
| `/dashboard` | Tableau de bord |
| `/servers` | Liste des serveurs |
| `/servers/create` | Ajouter un serveur |
| `/servers/{id}` | Détail + token agent |
