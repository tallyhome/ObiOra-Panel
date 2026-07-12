# Monitor vs Doctor vs Crash — guide utilisateur

ObiOra regroupe trois couches complémentaires. **Monitor** surveille et alerte ; **Doctor** diagnostique la santé système ; **Crash** (Analyzer + CrashHunter) explique les pannes et les freezes.

## Monitoring (Monitor+)

| Rôle | Contenu |
|------|---------|
| **Cible** | Disponibilité NOC : serveurs (ping, agent), sites/API (moniteurs), incidents, SLA |
| **Sources** | Panel (sondes HTTPS/Ping/Port), agent `obiora-metrics-push`, politiques d'alerte |
| **Quand l'utiliser** | « Est-ce up ? », uptime 24h/30j, latence, alertes opérationnelles |
| **URLs** | `/monitoring`, `/monitoring/servers/{id}`, `/monitoring/monitors/{id}` |

## Doctor & Suite

| Rôle | Contenu |
|------|---------|
| **Cible** | Santé interne : disque, RAM, services, SSL, MySQL, Virtualizor, score global |
| **Sources** | Agent Doctor (`obiora-doctor`), rapports signés, modules configurables |
| **Quand l'utiliser** | « Pourquoi c'est lent / plein / cassé ? », avant/après maintenance |
| **URL** | `/doctor` |

## Crash Analyzer

| Rôle | Contenu |
|------|---------|
| **Cible** | Événements kernel et reboots : OOM, panic, ECC, watchdog, journal boot |
| **Sources** | Agent crash-analyzer, métriques et rapports post-incident |
| **Quand l'utiliser** | Redémarrage inattendu, erreur matérielle, corrélation après alerte Monitor |
| **URL** | `/crash-analyzer` |

## CrashHunter

| Rôle | Contenu |
|------|---------|
| **Cible** | Freezes et stalls (RCU, I/O), snapshots témoin, witness inter-serveurs |
| **Sources** | Agent CrashHunter, witness maître↔slaves, incidents avec verdict |
| **Quand l'utiliser** | Serveur « gelé » mais ping OK, diagnostic forensics, comparaison witness |
| **URL** | Onglet CrashHunter dans Doctor & Suite |

## Chaîne Monitor+ (Phase 7)

```
Incident Monitor (ex. disk 100 %, serveur offline, freeze)
        │
        ├─► Fiche serveur unifiée /monitoring/servers/{id}
        ├─► Corrélations : Doctor disque, Crash Analyzer reboot, CrashHunter stall
        ├─► Rapport SLA HTML (30/60/90 j) — export depuis la fiche serveur
        └─► Intelligence alertes : fusion offline+agent, escalade après 15/60 min
```

## Règle simple

| Question | Outil |
|----------|-------|
| C'est accessible de l'extérieur ? | **Moniteurs** |
| Le serveur répond au ping / agent ? | **Monitoring serveurs** |
| Qu'est-ce qui consomme / remplit le disque ? | **Doctor** |
| Pourquoi le serveur a reboot ? | **Crash Analyzer** |
| Pourquoi tout s'est figé sans reboot ? | **CrashHunter** |

## Witness inter-serveurs

Le panel maître reçoit les signes de vie **witness** des agents CrashHunter sur chaque nœud. Si le **ping ICMP est OK** mais le **witness est mort**, une anomalie s'affiche sur le dashboard Monitoring et en Flotte avancée — symptôme typique d'un agent ou kernel bloqué alors que la stack réseau répond encore.
