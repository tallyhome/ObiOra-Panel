# ObiOra Slave

Installateur automatique de l'agent ObiOra sur un serveur distant (VPS, dédié).

## Installation one-liner

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)
```

## Ce que fait le script

1. Vérifie l'OS (Debian, Ubuntu, AlmaLinux, Rocky)
2. Installe PHP 8.3 CLI
3. Clone le dépôt ObiOra-Panel (dossier agent uniquement utilisé)
4. Génère une **clé API unique**
5. Configure et démarre le service `obiora-agent` (port 9100)
6. Ouvre le port dans UFW/firewalld si actif

## Liaison avec le panel maître

À la fin de l'installation, une **clé API** s'affiche dans le terminal.

Sur le panel maître :

1. **Serveurs** → **Ajouter un serveur**
2. Renseigner le nom, l'IP du slave
3. Coller la **clé API** affichée sur le slave
4. Enregistrer → **Ping** pour valider la liaison

## Options

```bash
bash Slave/install.sh --port 9100 --dir /opt/obiora-slave
```
