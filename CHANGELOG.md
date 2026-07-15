# Changelog

Toutes les modifications notables de ce projet sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/).

## v4.0.7 - 2026-07-15

### Correctif critique

- **Panel cassé après v4.0.6** : retrait de la route `demo.enter` référençant un contrôleur non livré (erreur fatale au boot, MAJ impossible)

## v4.0.6 - 2026-07-15

### Monitoring & alertes

- **Marquer lu OOM** : un clic ferme toutes les alertes OOM du serveur (plus bloquées par empreinte différente)
- **Flotte avancée** : alertes en pile pleine largeur, bouton « Tout marquer lu », CSS anti-chevauchement
- **Métriques serveur** : bannière si aucun échantillon agent + graphiques « Aucune donnée » explicites
- **Onglet onglets métriques** : correction Alpine.js (tabs CPU/RAM/Disk fonctionnels)

### Sécurité

- **Scan** : barre de progression + message par étape ; alerte si worker `obiora-queue` inactif

### Espace disque

- **Préférences → Rétention** : audit tailles (logs, vues, cache, Crash Analyzer, MariaDB…) + boutons purge

## v4.0.5 - 2026-07-15

### Récupération MariaDB / SSH

- **Script SSH** : `agent/scripts/panel-recover-ssh.sh` — redémarre MariaDB, resync mot de passe `.env`, git pull, migrate, caches
- **Diagnostic BDD** : message d'erreur SQL affiché sur la page 503 + JSON `/panel-health`
- **Health check** : test PDO direct (plus de faux « MariaDB KO » si TCP bloqué mais socket OK)

## v4.0.4 - 2026-07-15

### Correctifs critiques panel bloqué

- **503 permanent** : le panel ne bloque plus sur Redis — seule MariaDB est requise pour servir l'UI
- **Fallback cache** : si Redis est KO, bascule automatique sur `CACHE_STORE=database`
- **Diagnostic** : page 503 affiche MariaDB/Redis OK/KO + route `/panel-health` (JSON)
- **Timeouts** : sonde TCP rapide MariaDB/Redis (évite PHP-FPM bloqué)
- **Install petits VPS** : `CACHE_STORE=database` si ≤ 4 Go RAM

## v4.0.3 - 2026-07-15

### Correctifs & UX monitoring

- **OOM / Crash Analyzer** : déduplication par processus (plus par PID) — une seule alerte au lieu de dizaines pour le même OOM `crashhunter`
- **Statut Up/Down** : repères horaires sous la barre (1H, 6H, 24H…) + infobulle plage par segment
- **Graphiques serveur & flotte** : axes temps lisibles (CPU/RAM/disk, load average en courbes lisses, ping/score datetime)
- **Moniteur** : historique 200 checks chargé à la demande ; downsampling des séries temps de réponse
- **Scan sécurité** : lancement asynchrone (queue) — le panel ne bloque plus 5 min sur « démarrage en cours »
- **Witness dead** : aide install/réparation + lien Black Box (Doctor & Suite)
- **MariaDB petits VPS** : `innodb_buffer_pool_size` auto (256M si ≤4 Go RAM) — install + script `agent/scripts/mariadb-tune-panel.sh`
- **Page « démarrage en cours » bloquée** : cache santé DB/Redis avec retry 2 s (plus de worker PHP-FPM figé en 503) ; refresh 5 s + bouton Réessayer

## v4.0.2 - 2026-07-15

### Correctifs

- Migration `maintenance_windows` : colonnes `dateTime` au lieu de `timestamp` (MariaDB/MySQL — erreur 1067 `Invalid default value for ends_at`)
- Suppression du badge de test « test MAJ v2.7.19 » sur la page Licence & MAJ

## v4.0.1 - 2026-07-15

### Fix MAJ panel

- Overlay « mise à jour en cours » bloqué : laisser passer le polling Livewire (`/livewire/`) pendant le verrou `obiora-update.lock`
- Nouvelle commande `obiora:update-complete` : clôture `update_history` + suppression du lock depuis `update-panel.sh`
- Fallback UI si historique `running` à 100 % (worker queue mort) + import `File` manquant pour « Débloquer »

## v4.0.0 - 2026-07-15

### Monitor+ NOC (lots 1–4)

- **Maintenance / silences** : fenêtres planifiées (`/monitoring/maintenance`), suppression alertes et résolution incidents, API REST
- **Rétention** : purge `server_ping_samples` (job 04:30), CLI `obiora:prune --dry-run`, onglet Préférences → Rétention
- **Contacts** : bouton test par contact, onglet logs notifications (`/monitoring/alerts/notifications`)
- **Prometheus** : endpoint `/metrics` (Bearer token), doc `docs/monitoring/GRAFANA-PONT.md`
- **Profils dédiés génériques** : bare metal, Virtualizor, Proxmox, SolusVM, custom (install + UI serveur)

## v3.0.0 - 2026-07-15

- **Sécurité serveur** : page `/security` intégrée à Doctor (panel + slaves Obiora)
- Modules Doctor : `obiora`, `malware`, `accounts`, `persistence`, `privesc`, `auth_logs`, `web_perms`, `docker_security`, `lynis`, `mail_dns`, `waf`, `hosting_security`
- Enrichissement : ports/processus, SSL/TLS, CVE/kernel reboot, logs SSH brute-force, panels admin, backups
- Durcissement sûr uniquement : fail2ban, pare-feu, permissions, rkhunter (sans blocage SSH root/mot de passe)
- Scan périodique via agent Doctor + actions correctives depuis le panel

## v2.7.20 - 2026-07-15

- Install Virtualizor : détection auto, règles udev KVM (`65-kvm.rules`), SSH port 2212, proposition de reboot en fin d'install

## v2.7.19 - 2026-07-15

- Test pipeline MAJ panel (badge « test MAJ v2.7.19 » sur Licence & MAJ)

## v2.7.18 - 2026-07-15

- Crash Analyzer : dédoublonnage OOM (journal + dmesg + panel), cooldown rapports, résolution auto alertes après 60 min sans récidive

## v2.7.17 - 2026-07-15

- Fix install Doctor & Suite après MAJ : rechargement auto de `obiora-queue` si VERSION panel ≠ worker (code PHP gardé en mémoire)
- Script isolé `doctor-suite-local.sh` (sans curl) — chemin local séparé du déploiement distant
- `obiora:post-deploy --restart-queue` pour forcer le reload manuel après git pull
- MAJ panel : overlay plein écran au premier plan (plus de carte masquée derrière le contenu)
- Doctor & Suite : ObiOra Doctor visible dans le tableau « Contrôle des agents distants »

## v2.7.16 - 2026-07-15

- Fix install Doctor & Suite sur serveur local : exécution via `PrivilegedScriptRunner` (`sudo -n`) au lieu de `curl | sudo bash` (plus de mot de passe interactif depuis obiora-queue)

## v2.7.15 - 2026-07-15

- Fix agent Unauthorized : resync auto token agent.json + restart obiora-agent à la connexion
- Services locaux : liste baseline systemd en priorité (nginx, php-fpm, mariadb…)

## v2.7.14 - 2026-07-15

- Fix prod : `MasterServerSync` (serveur maître + agent.json + auto-réparation à la connexion)
- Commande `php artisan obiora:sync-master` pour réparer manuellement le VPS prod
- Services : fallback `systemctl-baseline.sh` (nginx, php-fpm, mariadb, redis, obiora-*)

## v2.7.13 - 2026-07-15

- Fix serveur maître absent après install : `SettingsSeeder` intégré à `obiora:post-deploy`
- Fix page Services vide : liste systemd via agent ou script sudo (`systemctl-list.sh`)
- Dashboard / métriques : le serveur maître est recréé automatiquement au post-deploy

## v2.7.12 - 2026-07-15

- Fix install AlmaLinux : conflit Nginx `duplicate default server` (nginx.conf + obiora-panel)
- Bootstrap install : messages clairs (git, GPG, clone) sans défilement brut dans PuTTY
- Docker / systemctl : sortie redirigée vers le journal pendant l'install
- MAJ panel : page « mise à jour en cours » (plus de flash HTTP 500) via verrou `obiora-update.lock`
- Dashboard : métriques locales si serveur maître absent ou session serveur invalide

## v2.7.11 - 2026-07-15

- Installateur : choix interactif au démarrage — install standard (1) ou complète + MAJ système (2)
- Options non interactives : `--mode standard|full`, `--full-upgrade`, `--no-prompt`

## v2.7.10 - 2026-07-15

- Installateur : sortie dnf/apt/composer/npm redirigée vers le journal (UI étapes visible dans PuTTY)
- Install plus rapide : plus de `dnf upgrade` complet par défaut (évite grub/kernel lent) — option `--full-upgrade`

## v2.7.9 - 2026-07-15

- Fix install « File not found » : permissions web avant PHP-FPM, redémarrage FPM, user pool dans groupe `obiora`
- Installateur : étapes numérotées + barre de progression %, vérification HTTP `/up` en fin d'install
- Log install documenté : `/var/log/obiora-install.log`

## v2.7.8 - 2026-07-13

- Fix installation Doctor & Suite locale : URL `curl` correctement quotée (échec silencieux)
- Witness flotte : « non installé » si CrashHunter absent (plus de fausse anomalie dead)
- Timeline Up/Down 1H : 60 segments fixes, prolongation verte après dernier check
- Métriques serveur : onglets CPU/Memory instantanés (Alpine, sans round-trip Livewire)
- Moniteur / serveur : indicateur « Chargement… » au changement de preset 1H/24H

## v2.7.7 - 2026-07-13

- Fix HTTP 500 après reboot VPS au login : vérification Redis au boot (page 503 au lieu de 500)
- Dashboard : métriques réseau tolérantes si Redis indisponible (mode dégradé)
- Boot systemd : PHP-FPM/Nginx attendent `obiora-panel-ready` (MariaDB + Redis prêts)
- Handler 503 si manifest Vite absent ou connexion Redis échoue

## v2.7.6 - 2026-07-12

- Moniteur site web : tableau « Derniers checks » pliable (fermé par défaut), jusqu'à 200 entrées

## v2.7.5 - 2026-07-12

- Fix graphiques monitoring : init ApexCharts centralisé (`monitoring-charts.js`) + re-render après morph Livewire
- Persistance preset (1H, 24H…) et onglet (Overview, CPU…) via paramètres URL au refresh
- Timeline Up/Down 1H : plus de segment gris « pas de données » en fin de période si le moniteur est up
- Witness : seuils par défaut 60s/90s (cycles agent ~45s ne marquent plus « dead » à tort)

## v2.7.4 - 2026-07-12

- Fix graphiques métriques serveur/moniteur vides : rendu ApexCharts inline + attente chargement module Vite

## v2.7.3 - 2026-07-12

- Fix presets 1H/6H métriques : requêtes en UTC (plus de fenêtre vide avec fuseau Europe/Paris)
- Graphiques : tooltips lisibles en mode nuit, points sur courbes (style Pinguzo), refresh données au changement de preset
- Witness : guide de remédiation « ping OK / witness mort » sur Flotte avancée

## v2.7.2 - 2026-07-12

- UI : onglets actifs lisibles (nav-tabs + nav-pills monitoring), débordement Flotte avancée corrigé
- Phase 7 complète : SLA uptime 30/60/90j + export rapport HTML, intelligence alertes (fusion + escalade)
- Witness CrashHunter sur dashboard et Flotte avancée (anomalie ping OK / witness mort)
- Corrélations enrichies (disk, reboot, freeze/stall) + doc `MONITOR-VS-DOCTOR-VS-CRASH.md`

## v2.7.1 - 2026-07-12

- Fix 500 page métriques serveur (`/monitoring/servers/{id}/metrics`) — erreur Blade dans le script graphiques
- Moniteurs : colonnes Uptime 24h, intervalle de check, bouton « Métriques »
- Page métriques moniteur style Pinguzo : presets temps, stats période, graphique réponse, timeline Up/Down

## v2.7.0 - 2026-07-12

- Monitoring Phase 7 (début) : fiche serveur unifiée `/monitoring/servers/{id}` (Doctor, Crash, incidents, liens Monitor+)
- Incidents : lien vers fiche serveur + boutons d'action contextuels (Doctor disque, Crash Analyzer, etc.)
- Liste serveurs : boutons « Fiche » et « Métriques »

## v2.6.0 - 2026-07-12

- Monitoring : compteur de visites site (pixel + snippet embed), métriques DNS/TCP/TTFB sur moniteurs
- Monitoring serveur : onglet Network (RX/TX kbps, connexions TCP, adresses IP agent)
- Flotte avancée : alertes en 2 colonnes compactes, bouton « Marquer lu » visible, install Doctor en 2 colonnes
- Phase 6 : status page publique `/status`, API REST `/api/v1/monitoring`, import/export JSON moniteurs, rétention 60j
- MAJ : migration auto (`track_token`, `monitor_visit_daily`, `status_page_settings`) via `migrate --force` dans `update-panel.sh`

## v2.5.0 - 2026-07-12

Release monitoring ObiOra (Phases 1 à 5, parité Pinguzo self-hosted).

- Hub `/monitoring` : dashboard, serveurs, moniteurs, incidents, alertes, préférences fuseau horaire
- Moniteurs HTTPS/HTTP/Ping/Port/Keyword/DNS, agent métriques push, graphiques serveur et moniteur
- Alertes Phase 5 : politiques configurables, contacts multi-canaux, incidents unifiés, notification logs
- MAJ panel : `migrate --force` puis `obiora:post-deploy` (RBAC, `AlertPolicySeeder`, caches, scripts agent) — **aucune commande manuelle sur le VPS**
- Installation agent monitor SSH automatisée + commande manuelle

## v2.1.68 - 2026-07-12

- Monitoring Phase 5 : politiques d'alerte (CRUD, 9 politiques Pinguzo seed), contacts multi-canaux (email, Slack, Discord, Telegram, webhook)
- Moteur `obiora:evaluate-alert-policies` (chaque minute) : incidents `monitoring_incidents`, durée persistante, repeat, recovery auto
- UI `/monitoring/alerts` + contacts, onglet Notification Logs sur Incidents, dashboard incidents unifié

## v2.1.67 - 2026-07-12

- Monitoring : installation agent SSH automatisée (IP, port, user, mot de passe) + commande manuelle
- Monitoring Phase 4 : graphiques serveur (`/monitoring/servers/{id}/metrics`) et courbe réponse moniteur

## v2.1.66 - 2026-07-12

- Monitoring Phase 3 : agent métriques ObiOra (push 1 min, queue offline, systemd timer, install one-liner avec --server-id)
- API `POST /api/v1/servers/{id}/monitor/metrics`, table `server_metric_samples`, statuts Online/Degraded/Offline

## v2.1.65 - 2026-07-12

- MAJ panel : `obiora:post-deploy` exécute désormais `optimize:clear` et `chmod +x` sur les scripts agent monitor
- Monitoring Phase 2 : moniteurs HTTPS/HTTP/Ping/Port/Keyword/DNS, sondes planifiées (`obiora:run-monitors`), historique checks, page métriques

## v2.1.64 - 2026-07-12

- Monitoring Phase 1 : hub unifié (`/monitoring`) avec dashboard type Pinguzo (compteurs serveurs, moniteurs placeholder, incidents)
- Monitoring : page Serveurs (liste enrichie, tags, SMART Doctor, clé agent copiable, modals Ajouter / Installer / Désinstaller)
- Monitoring : incidents ouverts, alertes (stub Phase 5), préférences fuseau horaire utilisateur, navigation sous-menu
- API `GET /api/monitoring/summary`, script public `/install/monitor-agent.sh`, doc `docs/monitoring/`

## v2.1.49 - 2026-07-11

- Doctor & Suite : fuseau horaire distant via SSH (serveurs Doctor sans agent slave)
- Doctor & Suite : contraste dark mode corrigé sur les blocs synthèse (fin du fond blanc illisible)
- MAJ panel : recompilation auto des assets si VERSION change (recovery + tampon build)

## v2.1.48 - 2026-07-11

- Crash Analyzer : layout compact, 6 graphiques (CPU/load, RAM, swap, PSI, réseau, température), badges collecteurs
- Crash Analyzer : bouton « Voir » sur les rapports post-crash (HTML lisible) + export PDF/HTML conservé
- Doctor & Suite : drill-down snapshots ring buffer CrashHunter + score de confiance diagnostic

## v2.1.47 - 2026-07-11

- Doctor & Suite : synthèse « Ce qui s'est passé » en langage clair (crash, freeze, OOM, recommandations)
- Doctor : affichage des recommandations sur les findings critiques
- CrashHunter : pistes de résolution affichées correctement (format actions[])

## v2.1.46 - 2026-07-11

- Doctor & Suite : option « Agent seedbox (slave) » décochée par défaut (dédié Virtualizor sans seedbox)
- Doctor & Suite : bouton « Supprimer agents et fichiers » — désinstallation complète (services, logs, snapshots, répertoires)
- Route publique `/install/uninstall-doctor-suite.sh` pour le nettoyage distant via SSH

## v2.1.45 - 2026-07-11

- Doctor & Suite : SSH mot de passe si IP cible ≠ serveur panel (fix clé sur mauvais hôte)
- Déploiement : échec deploy.log ne bloque plus le job ; permissions logs au recovery

## v2.1.44 - 2026-07-11

- CrashHunter : push incidents/rapports freeze live, timeline events, sync post-reboot
- CrashHunter : witness basé sur timestamp agent, graphiques et recommandations UI Doctor
- CrashHunter : job rétention BDD (PruneOldCrashHunterDataJob)

## v2.1.43 - 2026-07-11

- CrashHunter : intégration panel (Doctor & Suite, API ingest, tables BDD, déploiement SSH)
- ObiOra-Suite : CrashHunter v2.1.0 (witness, sysrq, perf, web UI, panel bridge, etc.)
- MAJ : migrations automatiques après `composer install` ; recovery applique aussi les migrations en attente

## v2.1.42 - 2026-07-11

- Login : correction 500 après reboot (page 503 « démarrage en cours » si MariaDB/Redis pas prêts)
- Boot : `obiora-panel-ready.service`, PHP-FPM/Nginx après MariaDB/Redis, script `panel-boot-wait.sh`
- i18n : locales FR/EN/DE/ES, switcher login + panel, cookie `obiora_locale`
- ObiOra-Suite : CrashHunter Enterprise v2.0 (silent freeze, incident mode, plugins, bundle OVH)
- UI : thème jour/nuit, logo SVG, navbar/sidebar harmonisés

## v2.1.41 - 2026-07-10

- Agent : `obiOra-agent` versionné exécutable dans Git (évite 644 après `git pull`)
- MAJ / recovery : `ensure_agent_executables` après checkout et au démarrage systemd (`ExecStartPre chmod`)

## v2.1.38 - 2026-07-10

- Doctor & Suite : installation directe sur le serveur local du panel (sans SSH vers sa propre IP)
- SSH distant : `HOME` et `known_hosts` dans `storage/app/ssh` pour PHP-FPM (apache)
- Script `ssh-authorize-panel-key.sh` pour bootstrap clé sur serveur local
- Sauvegardes : `mysqldump` réel, erreurs explicites, vérif MariaDB actif
- Install / recovery : création `/etc/obiora/mysql-admin.cnf` pour accès root socket aux backups

## v2.1.37 - 2026-07-10

- Crash Analyzer : journal persistant post-reboot, inventaire matériel enrichi
- Doctor & Suite : affichage statut agents et journal crash dans l'UI

## v2.1.36 - 2026-07-09

- Slave : installation / désinstallation Docker à distance via l'agent (menu Docker)
- Slave : marketplace transmet les options wizard (Nextcloud, mots de passe, BDD) à l'agent distant
- Vérification Docker sur serveur distant avant install d'apps conteneurisées
- Assistant IA : message explicite solde DeepSeek insuffisant (HTTP 402) et guide monitoring en secours

## v2.1.35 - 2026-07-09

- Serveurs : grille responsive, panneau SSH compact (%), fermeture auto après install slave
- Détection OS distante (AlmaLinux, Ubuntu…) via SSH test / déploiement
- Statut agents unifié (Seedbox / Doctor / Crash) dans flotte Doctor & Suite et fiche serveur
- UX ajout serveur : info « étape suivante » au lieu d'alerte alarmante

## v2.1.34 - 2026-07-09

- Doctor & Suite : liaison auto serveur panel par IP SSH (création si absent) — flotte / monitoring / crash à jour
- UI déploiement : console en `vh`, panneau fermé au retour sur la page, bouton Fermer
- Fix script `install-doctor-suite.sh` : `BASH_SOURCE` sous curl|bash

## v2.1.33 - 2026-07-09

- Doctor & Suite : déploiement via worker `obiora-queue` (plus de blocage à 5 % depuis PHP-FPM)
- Journal panel des déploiements (`deploy_logs` + `storage/logs/deploy.log`) visible dans l'UI
- Libellés « serveur dédié et/ou VPS » à la place de « VPS » uniquement
- Protection MAJ : intégrité fichiers critiques, CI `maj-integrity`, règle Cursor

## v2.1.32 - 2026-07-09

- Protection MAJ : vérification d'intégrité des fichiers critiques avant chaque mise à jour (`PanelUpdateIntegrity`)
- CI dédiée `maj-integrity` (syntaxe bash, scripts exécutables, tests MAJ)
- Règle Cursor `maj-protection` pour éviter de casser le pipeline lors des futurs changements
- Sudoers : accès NOPASSWD à `update-recover.sh` pour la récupération HTTP post-MAJ

## v2.1.31 - 2026-07-09

- Test release : badge « test MAJ » sur la page Licence & MAJ (validation du pipeline de mise à jour)

## v2.1.30 - 2026-07-09

- Fix 500 après MAJ : sauvegarde/restauration de `public/build` si `npm run build` échoue
- Récupération post-MAJ renforcée : composer, rebuild Vite, `obiora:post-deploy`, permissions storage

## v2.1.29 - 2026-07-09

- MAJ panel : plus de `restart` PHP-FPM pendant la mise à jour (évite 502 Bad Gateway sur le polling Livewire)
- MAJ panel : récupération HTTP automatique en fin de script et après échec (`install/lib/update-recover.sh`)
- Nouvelle commande `obiora:recover-panel-http` pour rétablir le panel manuellement après une MAJ bloquée

## v2.1.28 - 2026-07-09

- Serveurs : statut « en attente » tant que l'agent n'est pas installé (plus de faux offline ICMP)
- Serveurs : installation SSH automatique de l'agent seedbox slave (comme Doctor & Suite)
- Fix commande Doctor sur fiche serveur (curl panel au lieu de ObiOra-Doctor local)
- Slave/install.sh accepte `OBIORA_AGENT_TOKEN` depuis le panel

## v2.1.26 - 2026-07-09

- Doctor & Suite : workflow simplifié (test connexion → installer), clé SSH auto, vue flotte lisible

## v2.1.25 - 2026-07-09

- Doctor & Suite : mot de passe conservé après test SSH, déploiement activé après test réussi (clé auto-installée)

## v2.1.24 - 2026-07-09

- Doctor & Suite : auth SSH par mot de passe via phpseclib (plus besoin de sshpass sur le panel)

## v2.1.23 - 2026-07-09

- Monorepo : ajout du dépôt **ObiOra-Doctor** (moteur de diagnostic Linux, v0.4.0) + CI GitHub Actions

## v2.1.22 - 2026-07-09

- Crash Analyzer : install distant via curl (archive `/install/crash-analyzer.tar.gz` depuis le panel)
- Sidebar : liens Doctor & Suite + Crash Analyzer
- Doctor & Suite : déploiement SSH distant, vue flotte, script `/install/doctor-suite.sh`

## v2.1.21 - 2026-07-09

- Fix MAJ panel : `update-panel.sh` introuvable (bit +x git, helper setuid lit R_OK, fallback sudo bash, restauration git auto)

## v2.1.20 - 2026-07-09

- README : logo bannière en PNG (GitHub ne prend pas en charge les SVG dans les `<img>`)

## v2.1.19 - 2026-07-09

- README GitHub : bannière logo SVG professionnelle (`docs/assets/obiora-logo-banner.svg`)

## v2.1.18 - 2026-07-09

- Fix build Vite : dépendance `chart.js` pour le dashboard Crash Analyzer

## v2.1.17 - 2026-07-08

- Scheduler panel : installation automatique via `obiora-scheduler.timer` à chaque MAJ (plus de crontab manuel)

## v2.1.16 - 2026-07-08

- Fix critique : module Crash Analyzer manquant dans v2.1.14/v2.1.15 (erreur 500 `Invalid route action`)
- Agent Python, API ingest, dashboard Livewire, exports et migrations inclus

## v2.1.15 - 2026-07-08

- Compte client démo : accès lecture Licence & MAJ, maintenance système grisée (badge « Compte démo »)
- Rôle client : permissions `updates.view` et `license.view`

## v2.1.14 - 2026-07-08

- Rôle client : accès Docker (marketplace / installation apps)
- Comptes démo : blocage connexion + déconnexion auto à expiration, commande `obiora:expire-demo-accounts` (cron horaire via `obiora-scheduler.timer`)
- Bannière « Compte démo » avec date d'expiration dans le panel
- Post-déploiement automatisé : `php artisan obiora:post-deploy` (migrate + RolePermissionSeeder + permission:cache-reset) intégré à l'install et aux MAJ

## v2.1.13 - 2026-07-08

- Fix super-admin sans lien Monitoring : bypass Gate + migration sync permissions RBAC

## v2.1.12 - 2026-07-08

- Monitoring : bloc « Aide rapide » (systemd, disque, RAM, charge CPU) avec commandes de diagnostic
- Agent Doctor bootstrap : noms des unités systemd en échec dans le rapport

## v2.1.11 - 2026-07-08

- Fix lien Monitoring sidebar (affiché si permission `monitoring.view`, sans dépendre du flag module)
- Fix désinstallation Marketplace bloquée (recovery progression stale + reset état)
- Doc `docs/architecture/RBAC-MATRIX.md` — scope client multi-tenant en suspens (AdminLicence)

## v2.1.10 - 2026-07-08

- RBAC complet : matrice permissions admin / technicien / client (seeder + middleware routes + sidebar)
- Actions sensibles protégées (serveurs, services, docker, marketplace)

## v2.1.9 - 2026-07-08

- Profil utilisateur : menu déroulant (nom en haut à droite) → édition nom, email, mot de passe
- Monitoring enrichi : statut Doctor, avertissements, dernière activité, détail modules/findings, métriques en comparaison

## v2.1.8 - 2026-07-08

- Fix erreur 500 page Doctor & Suite (syntaxe Blade `@if` inline invalide)

## v2.1.7 - 2026-07-08

- Doctor & Suite : tableau tous serveurs + scores (rendu PHP direct)
- Monitoring : données initiales côté serveur + alerte si API 502
- MAJ : `php-fpm` redémarré en premier (évite Bad Gateway)

## v2.1.5 - 2026-07-08

- Fix Doctor HTTP 500 : champ `doctor_version` trop court (panel-bootstrap-1.0 > 16 car.)
- Agent bootstrap : version raccourcie `bootstrap-1.0`, migration colonne élargie
- Fix MAJ bloquée : suppression `artisan down` (cassait Livewire + poll), détection auto à 58 %
- Bannière erreur MAJ visible au-dessus de la barre de progression

## v2.1.4 - 2026-07-08

- Fix agent Doctor : bug bash `set -e` sur `(( score < 0 ))` qui faisait échouer le scan systemd
- Agent : meilleurs logs HTTP, test du premier scan obligatoire, fichier `agent.env`
- Boutons Copier (Monitoring + Doctor) : fallback HTTP sans API clipboard sécurisée
- Page Doctor : explication des variables OBIORA_PANEL_URL / SERVER_ID / AGENT_TOKEN

## v2.1.3 - 2026-07-08

- Licence & MAJ : historique (gauche) + journal des versions (droite) en 50/50
- Fix MAJ bloquée / erreur 500 : mode maintenance pendant update, purge caches au lieu de `optimize`, recovery plus rapide, bouton Débloquer
- Fix restart obiora-queue déplacé après succès du job (évite d'interrompre la MAJ en cours)

## v2.1.2 - 2026-07-08

- Modal logs MAJ responsive (92 % largeur viewport, max 78 % hauteur)
- Doctor & Suite : lien sidebar sous Sauvegardes (hors Infrastructure)
- Agent Doctor : bootstrap autonome + route publique `/install/doctor-agent.sh`, commandes copiables (local + distant)
- Monitoring : commandes d'installation dynamiques par serveur (token réel)
- Assistant IA : bulles lisibles, activé par défaut, mode local sans clé, providers DeepSeek / Kimi (Moonshot)
- Dashboard : poll désactivé par défaut si Reverb actif (temps réel WebSocket)

## v2.1.1 - 2026-07-08

- Fix MAJ panel bloquée à 58 % : purge caches routes/vues après git checkout, timeout npm build, recovery MAJ stale
- Fix erreur 500 Licence & MAJ : route stub `/modules/{slug}` avec contrainte vide (Phase 13)
- Reverb activé par défaut (install + MAJ) sauf `OBIORA_REALTIME_ENABLED=false`
- Job MAJ : marque failed si timeout/worker tué (`ApplyPanelUpdateJob::failed`)

## v2.1.0 - 2026-07-08

- Phase 13 : modules Infrastructure métier (SSL, Firewall, Users, Nginx, Redis, Apache, FTP, DNS, Applications, Virtualizor, Cluster)
- Page Doctor & Suite (`/doctor`) — agent Doctor, lien ObiOra-Suite
- IA : historique conversations BDD, actions chat (redémarrage service)
- Scripts agent infrastructure + gestion ports firewall
- Reverb : proxy Nginx `/app` réappliqué automatiquement à la MAJ si activé

## v2.0.2 - 2026-07-08

- Marketplace Webmin : ouverture port 10000 (firewalld/ufw), vérification service, écoute réseau
- Changelog intégré dans Licence & MAJ (parser CHANGELOG.md)
- Documentation Phase 13 (roadmap modules métier, Doctor/Suite, IA enrichie)

## v2.0.1 - 2026-07-08

- Phase 12 : assistant IA (`/ai`) avec contexte Doctor, OpenAI/Anthropic/Ollama, mode local sans clé
- Sidebar Infrastructure repliable (état mémorisé navigateur)
- Assistant IA sorti de la section Infrastructure (lien dédié)

## v2.0.0 - 2026-07-08

- Phase 11 : Laravel Reverb temps réel (opt-in, polling en repli)
- Pages stub Infrastructure sous `/modules/{slug}`
- Polish monitoring Vue, events dashboard/services/marketplace
- Service systemd `obiora-reverb`, proxy Nginx WebSocket `/app`

## v1.9.41 - 2026-07-07

- Marketplace : grille de cartes avec icones officielles (Homarr) et fallback lettre
- Fix File Browser : mots de passe via proprietes Livewire scalaires (setupPassword0/1)

## v1.9.40 - 2026-07-07

Fix définitif confirmation mot de passe File Browser : indices numériques Livewire, champs pass/pass2, tests unitaires.

## v1.9.39 - 2026-07-07

Fix confirmation mot de passe File Browser (setupPasswords + form submit).

## v1.9.20 - 2026-07-07

Corrige l'échec d'installation du helper setuid (`OBIORA_GROUP: unbound variable`) qui bloquait toute mise à jour. Le git sync s'exécute désormais avant l'installation du helper pour débloquer les serveurs coincés.

## v1.9.19 - 2026-07-07

Champs texte formulaires enfin lisibles (variables Bootstrap compilées avant import + classe obiora-input). Sauvegarde BDD corrigée (pipefail grep vide). Installation Docker avec barre de progression % et correction cache DNF corrompu.

## v1.9.18 - 2026-07-07

Correction définitive des mises à jour panel : binaire setuid `/usr/local/bin/obiora-panel-update` (plus de dépendance sudoers). Bouton « Installer Docker » dans le panel. Champs formulaire serveurs lisibles (texte visible sur fond sombre). Docker installé par défaut à l'installation.

## v1.9.17 - 2026-07-07

Corrige sauvegardes (`tar` + PATH sudo), sites web sur AlmaLinux/RHEL (`conf.d` au lieu de `sites-available`), marketplace install/désinstall via `marketplace-exec.sh`, et filtre les services panel (masque auditd et services système non gérables).

## v1.9.16 - 2026-07-07

Corrige « Échec de la mise à jour : sudo: a password is required » : le worker `obiora-queue` (utilisateur `obiora`) peut désormais exécuter `update-panel.sh` sans mot de passe, et l'ID de progression est passé en argument (plus via `env` qui bloquait sudoers).

## v1.9.15 - 2026-07-07

Corrige `sudo: a password is required` pour sauvegardes, sites web, bases MySQL, Docker et services : les scripts agent sont exécutés directement (plus via `bash`) pour correspondre aux règles sudoers. Ajout de scripts `systemctl-action.sh` et `systemctl-logs.sh` pour start/stop et journaux. Masquage des services systemd internes dans la liste.

## v1.9.14 - 2026-07-07

Corrige l'échec `vite build` sur les serveurs déjà installés : `npm ci`/`npm install` est désormais toujours exécuté avant `npm run build` (dépendance `sweetalert2` manquante dans node_modules).

## v1.9.13 - 2026-07-07

Logo SVG intégré directement dans le HTML (plus de fichier externe — fonctionne sur tous les serveurs). Widgets RAM et disque refaits style QuickBox avec icônes et libellés « utilisé / libre / total ». Correction du bouton « Mettre à jour » (SweetAlert + Livewire) et barre de progression avec % pendant l'installation depuis GitHub.

## v1.9.12 - 2026-07-07

Corrige le logo qui ne s'affichait pas après MAJ (SELinux bloquait silencieusement les nouveaux fichiers non relabellisés : `restorecon` ajouté à update-panel.sh). Le worker de file d'attente (`obiora-queue`) est désormais démarré automatiquement par le panel si besoin — plus aucune commande SSH à taper côté client.

## v1.9.11 - 2026-07-07

Corrige le bouton « Mettre à jour » qui ne faisait rien : la MAJ tournait de façon synchrone dans la requête HTTP et dépassait les timeouts PHP-FPM/Nginx (composer+npm+migrate peuvent prendre plusieurs minutes). Bascule sur une file d'attente (`obiora-queue`) : le clic lance immédiatement le job en arrière-plan, avec suivi en direct (statut, spinner, historique) via un polling toutes les 3 secondes.

## v1.9.10 - 2026-07-07

Logo SVG ObiOra Panel + SeedBox (sidebar, page login). Note explicative sur l'historique MAJ failed.

## v1.9.9 - 2026-07-07

Corrige le bouton Verifier (toast + chargement) et detecte les tags GitHub sans release.

## v1.9.8 - 2026-07-07

Dashboard bande passante temps reel et layout Swizzin.

## v1.9.7 - 2026-07-07

Corrige les echecs de MAJ quand le depot serveur a des fichiers modifies localement (git reset --hard origin/main).

## v1.9.6 - 2026-07-07

Corrige l'erreur **Call to undefined method ProcessResult::output()** lors du clic Mettre a jour sur la page Licence et MAJ.

## v1.9.5 - 2026-07-07

### Correctifs

- Sites web / Bases / Docker : scripts agent via sudo -n (PHP-FPM apache)
- Sudoers apache/nginx sur agent/scripts + /var/www
- Suppression des entrees error/pending meme si deprovisionnement echoue
- Provisionnement website-create.sh (permissions, socket PHP Remi)

### Ameliorations

- SweetAlert2 pour toasts et confirmations
- update-panel.sh reapplique sudoers automatiquement

## v1.9.4 - 2026-07-07

Corrige erreur 500 lors de la mise a jour depuis le panel. Script install/update-panel.sh + sudoers pour PHP-FPM. Dashboard refresh 10s.

## v1.9.3 - 2026-07-07

Dashboard refonte style Swizzin/QuickBox: theme sombre, widgets systeme, barres de progression, services cles, auto-refresh 30s.

## v1.9.2 - 2026-07-07

Corrige la detection des mises a jour (VERSION, git, GitHub API) et affiche un bandeau + badge sidebar.

## v1.9.0 - 2026-07-07

Phase 10: page Licence et MAJ, integration AdminLicence, correctifs installateur (404, permissions, SELinux).

## [1.9.9] - 2026-07-07

### Corrigé

- **Vérifier les MAJ** : toast SweetAlert2 + indicateur de chargement + horodatage de la dernière vérification
- **Détection MAJ** : prise en compte des tags GitHub (ex. v1.9.8) même sans release publiée

## [1.9.8] - 2026-07-07

### Amélioré

- **Dashboard** : graphique bande passante temps réel (poll 3s) au-dessus de la charge CPU
- **Layout Swizzin** : colonne droite avec En un coup d'œil, RAM, Disque, puis Network Info (Interface, Span, historique journalier)

## [1.9.7] - 2026-07-07

### Corrigé

- **update-panel.sh** : `git reset --hard origin/main` si le dépôt a des modifications locales (évite l'échec du pull sur le serveur)

## [1.9.6] - 2026-07-07

### Corrigé

- **Mise à jour panel (500)** : `PanelUpdater` utilisait `output()` / `successful()` comme méthodes au lieu des propriétés de `ProcessResult`

## [1.9.5] - 2026-07-07

### Corrigé

- **Sites web / Bases / Docker** : scripts agent exécutés via `sudo -n` (PHP-FPM `apache` sans mot de passe)
- **Sudoers** : `apache`/`nginx` autorisés sur `agent/scripts/*.sh` + création `/var/www` à l'install/MAJ
- **Suppression** : entrées en erreur ou en attente retirables même si le déprovisionnement serveur échoue
- **Provisionnement** : `website-create.sh` corrigé (permissions, socket PHP Remi/RHEL)

### Amélioré

- **Notifications** : SweetAlert2 (toasts + confirmations) à la place des `alert` / `wire:confirm` natifs
- **MAJ panel** : `update-panel.sh` réapplique la configuration sudoers automatiquement

## [1.9.4] - 2026-07-07

### Corrigé

- **Mise à jour panel (500)** : import manquant + script `install/update-panel.sh` exécuté via sudo (PHP-FPM ne peut pas faire git/composer directement)
- **Sudoers** : autorise `apache`/`nginx` à lancer `update-panel.sh` sans mot de passe
- **Dashboard** : rafraîchissement auto toutes les **10 secondes** (badge Live)

## [1.9.3] - 2026-07-07

### Amélioré

- **Dashboard seedbox** : thème sombre inspiré Swizzin / QuickBox (widgets, barres de progression, uptime, services clés)
- **Navigation** : sidebar repensée, raccourcis Marketplace / Services / Sites / Docker
- **Auto-refresh** : métriques actualisées toutes les 30 secondes

## [1.9.2] - 2026-07-07

### Corrigé

- **Mises à jour** : détection fiable via fichier `VERSION`, tag git et commits en retard sur `main`
- **GitHub API** : en-têtes requis + repli sur la liste des releases si `/releases/latest` échoue
- **UI** : bandeau « Mise à jour disponible », badge `!` dans la sidebar, messages d'erreur API visibles
- **PanelUpdater** : `config:clear` après mise à jour, détection hors strict `/opt/`

## [1.9.1] - 2026-07-07

### Corrigé

- **Serveurs** : page détail « Local Server » — vue `server-show` complète (corrige erreur 500)
- **Sites web / Bases de données** : nouvelle tentative possible après échec (suppression des entrées `pending`/`error` fantômes)

## [1.9.0] - 2026-07-07

### Phase 10 — AdminLicence & Mises à jour

#### Ajouté

- Page **Licence & MAJ** (`/settings`) : activation licence, sync AdminLicence, vérification GitHub Releases
- `AdminLicenceClient`, `LicenseService`, `PanelUpdater`
- Historique des mises à jour (`update_history`)
- Menu sidebar « Licence & MAJ »
- Documentation [PHASE-10.md](docs/architecture/PHASE-10.md)

#### Corrigé (installateur v1.8.10 → inclus en 1.9.0)

- **404 « File not found »** : permissions web (`nginx`/`apache` dans groupe `obiora`), détection socket PHP-FPM Remi/RHEL, SELinux
- Sync IP/hostname du serveur maître après installation
- Message post-install enrichi (URL `/setup`, étapes suivantes)

## [1.8.9] - 2026-07-07

### Corrigé

- **systemd timer** : `OnCalendar=minutely` à la place de `* * * * *` (syntaxe cron invalide) — corrige `bad unit file setting` sur AlmaLinux
- **Nginx** : `default_server` + désactivation de `default.conf` RHEL — corrige le conflit `server_name "_"`
- **Réinstallation** : conserve `APP_KEY` et saute npm si les assets sont déjà compilés
- **Supervisor** : démarrage optionnel (n'interrompt plus l'install)

## [1.8.8] - 2026-07-07

### Corrigé

- **Nginx sur RHEL/AlmaLinux** : écriture directe dans `/etc/nginx/conf.d/` quand `sites-available/` n'existe pas (convention Debian) — corrige `No such file or directory` sur AlmaLinux

## [1.8.7] - 2026-07-07

### Corrigé

- **Redis Connection refused** : Redis est démarré **avant** les migrations (auparavant lancé à `setup_systemd`, après `setup_laravel`) — corrige l'échec de la migration `permission_tables` (reset de cache spatie sur `CACHE_STORE=redis`)

## [1.8.6] - 2026-07-07

### Corrigé

- **DB Access denied** : `ALTER USER` force la synchro du mot de passe (au lieu de `CREATE USER IF NOT EXISTS` qui l'ignore si l'utilisateur existe), et réutilisation du mot de passe existant sur réinstallation
- **Host DB** : création de l'utilisateur pour `localhost` **et** `127.0.0.1` (connexion TCP Laravel)

## [1.8.5] - 2026-07-07

### Corrigé

- **composer.lock incompatible PHP 8.3** : plateforme figée à `php 8.3.0` dans `composer.json`, lock régénéré vers Symfony 7.x — corrige `requires php >=8.4.1 -> your php version (8.3.32) does not satisfy` sur AlmaLinux
- **Réinstallation** : mise à jour du dépôt robuste (`git checkout -B main origin/main`) — corrige `pathspec 'main' did not match` sur un ancien clone shallow

## [1.8.4] - 2026-07-07

### Corrigé

- **composer: command not found** : `setup_laravel` préserve désormais le `PATH` (incluant `/usr/local/bin`) lors des commandes exécutées via `sudo -u obiora` — corrige l'échec sur AlmaLinux/RHEL (secure_path)
- **Installation sur `main`** : `OBIORA_TAG` vide par défaut — évite le warning `is not a commit` et l'état `detached HEAD` liés aux tags annotés

## [1.8.3] - 2026-07-07

### Corrigé

- **clone_panel** : `git clone` en root vers `/opt/obiora-panel` (l'utilisateur `obiora` ne peut pas créer de dossier dans `/opt`) — corrige `Permission denied` en cas de réinstallation

## [1.8.2] - 2026-07-06

### Corrigé

- **Installateur one-liner** : détection explicite `/dev/fd/*` — corrige l'erreur `/dev/fd/lib/common.sh`
- Même correctif appliqué à `Slave/install.sh`

## [1.8.1] - 2026-07-06

### Catalogue Swizzin + correctif installateur

#### Ajouté

- **68 applications** marketplace (catalogue Swizzin complet)
- Générateur `tools/generate-packages.php`
- Helper Docker partagé `packages/_lib/docker.sh`

#### Corrigé

- Bootstrap install.sh (première version auto-clone)

## [1.8.0] - 2026-07-06

### Phase 9 — Marketplace / Plugins

#### Ajouté

- Marketplace `/plugins` — installation apps en un clic depuis le dashboard
- Catalogue extensible `packages/` (style Swizzin, réécriture propriétaire)
- Apps initiales : Netdata, Jellyfin, Plex, Sonarr, qBittorrent
- `ApplicationCatalog`, `ApplicationManager`, table `installed_applications`
- API agent : `/api/v1/applications/install` et `uninstall`
- Sudoers étendu pour scripts `packages/*/install.sh`
- Menu « Marketplace » dans la sidebar

## [1.7.0] - 2026-07-06

### Phase 8 — Sauvegardes

#### Ajouté

- Module Backup : création, liste, détail, suppression
- Types : base de données, fichiers, complète
- Restauration SQL pour dumps `.sql.gz`
- Table `backups` liée aux serveurs
- `BackupManager` et `BackupProvisioner` (local + agent distant)
- Scripts `agent/scripts/backup-*.sh`
- API agent : `/api/v1/backups` et `/api/v1/backups/restore`
- Menu « Sauvegardes » dans la sidebar

## [1.6.0] - 2026-07-06

### Phase 7 — Docker

#### Ajouté

- Module Docker : conteneurs, images, logs, run
- `DockerManager` local + distant via agent
- Scripts `agent/scripts/docker-*.sh`
- API agent : endpoints `/api/v1/docker/*`
- Menu « Docker » dans la sidebar
- Formulaire rapide `docker run` (image, nom, ports)

## [1.5.0] - 2026-07-06

### Phase 6 — Bases de données MySQL/MariaDB

#### Ajouté

- Module MySQL : création, liste, détail, suppression de bases
- Scripts `agent/scripts/mysql-*.sh` (create, delete, list)
- Table `managed_databases` avec mots de passe chiffrés
- `DatabaseManager` et `DatabaseProvisioner` (local + agent distant)
- API agent : endpoints `/api/v1/databases`
- Menu « Bases de données » dans la sidebar
- Sudoers agent (`install/lib/sudoers.sh`) pour scripts sans mot de passe

## [1.4.0] - 2026-07-06

### Phase 5 — Sites web (Nginx, PHP, SSL)

#### Ajouté

- Module Websites : création, liste, détail, suppression
- Provisionnement Nginx + PHP-FPM via scripts `agent/scripts/`
- SSL Let's Encrypt (certbot) à la création ou après coup
- Table `websites` liée aux serveurs
- `WebsiteManager` et `WebsiteProvisioner` (local + agent distant)
- API agent : endpoints `/api/v1/websites` et `/api/v1/websites/ssl`
- Menu « Sites web » dans la sidebar

#### Installation

**Master :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)






















```
**Slave :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)






















```
## [1.3.0] - 2026-07-06

### Phase 4 — Slave installer & Services systemd

#### Ajouté

- Répertoire `Slave/` avec installateur one-liner pour serveurs distants
- Génération automatique de clé API sur le slave
- Liaison maître par clé API (plus de token généré côté maître)
- Module Services : liste, start/stop/restart, logs journalctl
- `ServiceManager` local + distant via agent
- API agent étendue : services, logs, ping enrichi (hostname, IP, OS)
- Menu Services dans la sidebar

#### Installation

**Master :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)






















```
**Slave :**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)






















```
## [1.2.0] - 2026-07-06

### Phase 3 — Dashboard, Auth & Multi-serveurs

#### Ajouté

- Authentification Livewire (login, logout, rate limiting)
- Wizard setup premier admin (`/setup`)
- Dashboard Livewire avec métriques système et ApexCharts
- Module multi-serveurs : liste, ajout, ping, sélecteur de serveur actif
- `ServerManager`, `MetricsCollector`, `AgentExecutor`
- Agent HTTP (`agent/public/index.php`) — ping + métriques
- Layout Bootstrap 5.3 avec sidebar
- Tests Feature setup/auth
- Sync token agent à l'installation

## [1.1.0] - 2026-07-06

### Phase 2 — Installation automatique

#### Ajouté

- Script `install/install.sh` complet (one-liner curl)
- Modules bash : detect-os, prerequisites, packages, database, laravel, nginx, ssl, systemd, firewall, rollback
- Support Debian, Ubuntu, AlmaLinux, Rocky Linux
- Installation : Nginx, PHP 8.3, MariaDB, Redis, Composer, Node 20, Supervisor, Certbot, Fail2Ban, UFW/firewalld
- Options `--docker`, `--ftp`, `--domain`, `--email`, `--tag`
- Services systemd : queue worker, scheduler, agent
- Script `install/uninstall.sh`
- `composer.lock` et `package-lock.json` générés

## [1.0.1] - 2026-07-06

### Phase 1 — Architecture

#### Ajouté

- Structure Laravel 12 avec architecture modulaire custom (`Modules/`)
- 23 modules stub (Dashboard, Servers, Services, Websites, Nginx, Apache, PHP, MySQL, Redis, Docker, Firewall, FTP, DNS, SSL, Backup, Monitoring, Users, Applications, Plugins, Cluster, Virtualizor, Updates, AI)
- Système core : `ModuleManager`, `UpdateManager`, `LicenseManager`, `ApplicationInstaller`
- Couche d'exécution système : `LocalExecutor` + contrat `SystemExecutorInterface`
- Migrations core : serveurs, nœuds, modules, licences, settings, logs, historique updates
- RBAC avec Spatie Permission (rôles : super-admin, admin, technician, client)
- API health endpoint `/api/v1/health`
- Frontend Bootstrap 5.3 + ApexCharts (préparation Livewire Phase 3)
- Stubs installation (`install/`) et agent (`agent/`)
- Support OS documenté : Debian, Ubuntu, AlmaLinux, Rocky Linux
- Licence propriétaire ObiOra

#### Notes

- Mises à jour via GitHub Releases (AdminLicence en Phase 10)
- Dashboard complet et authentification : Phase 3 (v1.2.0)
