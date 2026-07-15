# Phase 7 — Plus qu’ObiOra (différenciation)

**Objectif** : dépasser Pinguzo en fusionnant monitoring « NOC » et capacités uniques ObiOra (Doctor, Crash, forensics).

**Durée estimée** : continu / 2+ semaines  
**Prérequis** : Phases 1–6

---

## 1. Vision « Monitor+ »

Pinguzo = **surveiller et alerter**.  
ObiOra = **surveiller, alerter, diagnostiquer, comprendre, réparer**.

```
Incident Monitor (disk 100%)
        │
        ├─► Lien « Lancer Doctor » → rapport modules disk/mysql
        ├─► Lien « Voir Crash events » → journal 24h
        ├─► Lien « CrashHunter incident » → snapshots freeze
        └─► Actions panel : cleanup backups, docker prune, …
```

---

## 2. Fiche serveur unifiée

Une page `/monitoring/servers/{id}` avec onglets :

| Onglet | Source |
|--------|--------|
| Metrics | Phase 4 |
| Doctor | Dernier score, findings critiques |
| Crash | Events 24h, graphiques |
| CrashHunter | Incidents, witness, verdicts |
| Actions | Services, reboot, timezone, deploy agents |

Fin de la navigation fragmentée Monitoring / Doctor / Crash séparés.

---

## 3. Corrélations automatiques

| Événement Monitor | Action ObiOra |
|-------------------|---------------|
| Server offline | Ping + last Crash event + last Doctor |
| High disk | Doctor module disk + liste backups panel |
| Monitor Down HTTPS | Doctor SSL + curl depuis panel |
| Reboot detected | Crash Analyzer boot journal + Doctor reboot module |
| Agent no data | Vérifier `obiora-agent.service` + chmod agent (v2.1.41) |
| Freeze incident CrashHunter | Incident Monitor lié + notification prioritaire |

---

## 4. SLA & rapports

- **Uptime %** serveur et moniteur (30/60/90 jours) — export PDF
- Rapport hebdomadaire email : incidents, uptime, top alerts
- Comparaison périodes (Pinguzo « Comparative Insights »)

---

## 5. Intelligence alertes

Réduire bruit :

- Corréler `server_offline` + `agent_no_data` → un seul incident
- Supprimer alerte disk si Doctor confirme partition temporaire
- Escalade : warning 15 min → critical 60 min

---

## 6. Intégration profils hôte dédié (générique)

Au-delà de Virtualizor, chaque serveur **dédié** peut avoir un profil hôte :

| Profil | Usage |
|--------|--------|
| `bare_metal` | OVH, Hetzner, SoYouStart… |
| `virtualizor` | Nœud KVM Virtualizor |
| `proxmox` | Proxmox VE |
| `solusvm` | SolusVM |
| `custom` | Autre hyperviseur |

Métadonnée serveur : `metadata.host_profile`. Install panel : `--host-profile` ou détection auto.

Voir [ROADMAP-MONITOR-GRAFANA-DEDIE.md](./ROADMAP-MONITOR-GRAFANA-DEDIE.md) §2.

---

## 7. Witness inter-serveurs

CrashHunter witness → afficher dans Monitor dashboard :

- Maître panel witness slaves
- Alerte si slave dead alors que ping OK (agent crash)

---

## 8. Roadmap post-Phase 7

| Idée | Valeur |
|------|--------|
| Mobile PWA alerts | Push notifications |
| Runbook intégré par type incident | Réduction MTTR |
| IA résumé incident (DeepSeek panel) | Post-mortem auto |
| Plugin marketplace sonde custom | Écosystème |

---

## 9. Critères d’acceptation Phase 7

- [x] Depuis incident disk → accès Doctor disk findings en 1 clic
- [x] Fiche serveur unifiée remplace 3 menus pour usage courant
- [x] Rapport uptime exportable (HTML 30/60/90j)
- [x] Documentation utilisateur « Monitor vs Doctor vs Crash » clarifiée

---

## 10. Positionnement commercial

Message :

> **Pinguzo surveille. ObiOra surveille et soigne.**

Self-hosted, pas d’abonnement edge, données chez le client, forensics inclus.
