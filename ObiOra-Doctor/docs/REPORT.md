# Rapport complet — Obiora Doctor v0.3.0

Ce document decrit **tout ce que fait** Obiora Doctor une fois le projet termine.

---

## 1. Vue d'ensemble

**Obiora Doctor** est un moteur de diagnostic Linux professionnel, premier outil de **Obiora Suite**. Il analyse un serveur Linux en lecture seule, produit un **Health Score** (0-100), genere des rapports multi-formats et explique les problemes detectes avec des recommandations.

| Composant | Role |
|-----------|------|
| **Obiora Doctor** | Diagnostic complet serveur |
| **Obiora Watch** | Monitoring temps reel (1 s) |
| **Obiora Bench** | Benchmarks CPU/RAM/disque/reseau |
| **Obiora Rescue** | Plan de depannage lecture seule |
| **Obiora Agent** | Scans periodiques automatiques |
| **API REST** | Consultation des rapports en local |

---

## 2. Commandes disponibles

| Commande | Description |
|----------|-------------|
| `scan` | Diagnostic complet, tous modules actifs |
| `scan --module cpu` | Un ou plusieurs modules specifiques |
| `scan --exclude-module benchmark` | Exclure des modules |
| `scan --support` | Rapport anonymise (IP, domaines, secrets) |
| `scan --json` | Sortie JSON sur stdout |
| `scan --zip` | Export zip du rapport |
| `scan --quiet` | Pas de resume terminal |
| `scan --verbose` | Logs detailles |
| `list-modules` | Liste les 25 modules actifs |
| `interactive` | Menu terminal numerique |
| `watch` | Rafraichissement toutes les secondes |
| `compare <A> <B>` | Compare deux rapports |
| `history` | Liste l'historique des scans |
| `clean --days 30` | Supprime les rapports > 30 jours |
| `api` | Serveur REST local (port 8765) |
| `bench` | Benchmarks dedies |
| `agent --interval 300` | Agent de scan periodique |
| `agent --once` | Un seul scan agent |
| `rescue` | Plan de depannage sans action auto |
| `rescue --from-report <path>` | Plan depuis un rapport existant |

**Entree Linux :** `./obiora-doctor.sh scan`

---

## 3. Les 25 modules de diagnostic

### Systeme

| Module | Ce qu'il verifie |
|--------|------------------|
| **cpu** | `lscpu`, load average `/proc/loadavg` |
| **ram** | RAM totale/disponible, pression memoire, swap |
| **swap** | Swap configure, `vm.swappiness` |
| **disk** | `df` espace/inodes, `lsblk` topologie |
| **smart** | `smartctl --scan`, sante disques, echecs SMART |
| **raid** | `/proc/mdstat`, tableaux RAID degrades |
| **network** | `ip addr/route`, stats RX/TX, ports `ss` |
| **kernel** | `uname`, `dmesg` err/warn, `systemctl --failed` |

### Virtualisation & conteneurs

| Module | Ce qu'il verifie |
|--------|------------------|
| **docker** | Daemon, conteneurs, etats unhealthy/restarting |
| **kvm** | libvirtd/virtqemud, `virsh list`, VMs KVM |
| **lxc** | `lxc-ls`, `lxc list` (LXD) |
| **virtualizor** | Service Virtualizor, libvirt, inventaire VMs |

### Bases de donnees & web

| Module | Ce qu'il verifie |
|--------|------------------|
| **mysql** | mysqld/mariadb systemd, version client |
| **postgresql** | Service postgresql, version `psql` |
| **php** | Version PHP, PHP-FPM actif |
| **apache** | httpd/apache2 service et version |
| **nginx** | Service, version, `nginx -t` |
| **litespeed** | lshttpd/lsws actif |
| **laravel** | Detection `artisan`, `.env`, APP_DEBUG |

### Hebergement & securite

| Module | Ce qu'il verifie |
|--------|------------------|
| **cpanel** | `/usr/local/cpanel`, service, whmapi |
| **plesk** | Binaire plesk, sw-cp-server |
| **directadmin** | `/usr/local/directadmin`, statut |
| **firewall** | firewalld, ufw, nftables |
| **security** | SSH root login, password auth, SELinux |
| **benchmark** | Micro-benchmarks CPU/RAM/disque integres |

---

## 4. Health Score

Chaque module retourne un score **0-100** :

| Plage | Signification |
|-------|---------------|
| 90-100 | Sain |
| 70-89 | Acceptable avec recommandations |
| 40-69 | Degrade |
| 0-39 | Critique |

**Calcul :** score global = moyenne des scores modules. Penalites : -35 par CRITICAL, -12 par WARNING.

**Severites :** `INFO`, `WARNING`, `CRITICAL`

---

## 5. Rapports generes

Chaque scan cree un dossier horodate :

```
reports/2026-07-07T20-00-00+00-00/
  report.json    — donnees machine + knowledge base
  report.md      — lecture humaine
  report.html    — rapport client (theme sombre)
  report.txt     — sortie texte
```

**Mode support (`--support`) :** IP, domaines, hostname et secrets rediges.

**Export zip (`--zip`) :** archive du dossier rapport.

**JSON enrichi :** chaque finding inclut `probable_cause`, `suggested_action`, `documentation` via la base de connaissances.

---

## 6. Moteur technique

### Architecture

```
CLI (obiora-doctor.py / obiora-doctor.sh)
  → DiagnosticEngine
    → Module Registry (25 modules + plugins dynamiques)
    → CommandRunner (timeout, pas de shell=True)
    → Knowledge Base (causes et actions)
    → Report Renderer (JSON/MD/HTML/TXT)
```

### Fichiers cles

| Chemin | Role |
|--------|------|
| `core/engine.py` | Orchestration des modules |
| `core/runner.py` | Execution securisee des commandes |
| `core/reports.py` | Generation des rapports |
| `core/knowledge.py` | Base de connaissances |
| `core/redact.py` | Anonymisation |
| `core/compare.py` | Comparaison de scans |
| `core/watch.py` | Mode monitoring |
| `core/bench.py` | Benchmarks dedies |
| `core/agent.py` | Agent periodique |
| `core/rescue.py` | Plan de depannage |
| `core/api.py` | API REST stdlib |
| `core/plugins.py` | Chargement plugins dynamiques |
| `config/default.json` | Configuration globale |
| `config/modules.json` | Activation par module |

### Detection OS

AlmaLinux, Rocky Linux, Ubuntu, Debian, CentOS, RHEL, Fedora via `/etc/os-release`.

### Securite

- **Lecture seule** par defaut sur tous les modules
- Pas de `rm -rf`, pas de modification systeme
- Obiora Rescue = recommandations uniquement
- Anonymisation pour envoi support client

---

## 7. Obiora Suite — outils integres

| Outil | Commande | Fonction |
|-------|----------|----------|
| Doctor | `scan` | Diagnostic complet |
| Watch | `watch` | Monitoring 1s + historique `cache/watch/` |
| Bench | `bench` | CPU ops/s, RAM MB/s, disque MB/s, latence reseau |
| Rescue | `rescue` | Plan de depannage structure |
| Agent | `agent` | Scans auto + heartbeat `cache/agent/heartbeat.json` |
| Monitor | `api` | API REST pour future interface web |

---

## 8. API REST locale

Demarrage : `python bin/obiora-doctor.py api`

| Endpoint | Retour |
|----------|--------|
| `GET /health` | Statut service |
| `GET /reports` | Liste des rapports |
| `GET /reports/latest` | Dernier rapport JSON |
| `GET /reports/<dossier>` | Rapport specifique |

Defaut : `http://127.0.0.1:8765`

---

## 9. Plugins

Placer un fichier `.py` dans `plugins/` heritant de `DiagnosticModule`. Le moteur le charge automatiquement au demarrage.

Voir `docs/plugin-sdk.md` et `plugins/README.md`.

---

## 10. Tests

```bash
python -m unittest discover -s tests -v
```

13 tests couvrant : scoring, runner, rapports, redaction, comparaison, config, schema, knowledge, rescue, benchmark, registre modules.

---

## 11. Arborescence complete

```
ObiOra-Doctor/
  bin/obiora-doctor.py       # Executable Python
  obiora-doctor.sh           # Wrapper Linux
  config/
    default.json             # Config globale
    modules.json             # Activation modules
  core/                      # Moteur (15 fichiers)
  modules/                   # 25 modules + helpers
  plugins/                   # Extensions externes
  templates/report.html      # Template HTML
  reports/                   # Rapports generes
  cache/                     # Watch, agent, benchmarks
  logs/                      # obiora-doctor.log
  tests/test_core.py         # Tests unitaires
  docs/                      # Documentation complete
```

---

## 12. Utilisation typique

### Premier diagnostic sur un serveur Linux

```bash
cd ObiOra-Doctor
./obiora-doctor.sh scan
```

### Rapport support client

```bash
./obiora-doctor.sh scan --support --zip
```

### Monitoring continu

```bash
./obiora-doctor.sh watch --module cpu --module ram --module disk
```

### Comparer avant/apres intervention

```bash
./obiora-doctor.sh compare reports/scan-avant reports/scan-apres
```

### Agent sur VPS (scan toutes les 5 min)

```bash
./obiora-doctor.sh agent --interval 300
```

---

## 13. Ce qui reste pour les versions futures

- Dashboard web Obiora Monitor (Vue.js/Laravel)
- Agent distant multi-serveurs
- Benchmarks IOPS disque avances (fio)
- Modules WHMCS, Redis, Memcached
- Chargement plugins sans modification du registre
- CI ShellCheck automatise

---

*Rapport genere le 2026-07-07 — Obiora Doctor v0.3.0*
