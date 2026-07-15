# ObiOra Monitor — Cahier des charges

Enrichissement de la section **Monitoring** du panel ObiOra pour atteindre (puis dépasser) l’expérience produit [Pinguzo](https://pinguzo.com/features/), tout en conservant les atouts existants : Doctor, Crash Analyzer, CrashHunter, gestion multi-serveurs intégrée.

## Documents

| Fichier | Contenu |
|---------|---------|
| [00-ETAT-DES-LIEUX.md](./00-ETAT-DES-LIEUX.md) | Inventaire ObiOra actuel vs Pinguzo |
| [01-REFERENCE-PINGUZO.md](./01-REFERENCE-PINGUZO.md) | Analyse UX/UI et fonctionnelle (screens + doc publique) |
| [02-AGENT-INSPIRATION.md](./02-AGENT-INSPIRATION.md) | Architecture agent Pinguzo — inspiration uniquement |
| [MONITOR-VS-DOCTOR-VS-CRASH.md](./MONITOR-VS-DOCTOR-VS-CRASH.md) | Monitor vs Doctor vs CrashHunter |
| [RETENTION-ET-PURGE.md](./RETENTION-ET-PURGE.md) | **Rétention & purge** — métriques, logs, traces, rapports |
| [PHASE-1-fondations-dashboard.md](./PHASE-1-fondations-dashboard.md) | **Phase 1** — Dashboard, navigation, serveurs, préférences |
| [PHASE-2-moniteurs-externes.md](./PHASE-2-moniteurs-externes.md) | Phase 2 — Sites / API / Ping / Port / Keyword / DNS |
| [PHASE-3-agent-metriques-serveur.md](./PHASE-3-agent-metriques-serveur.md) | Phase 3 — Agent métriques unifié (style Pinguzo) |
| [PHASE-4-graphiques-metriques.md](./PHASE-4-graphiques-metriques.md) | Phase 4 — Pages métriques serveur & site (graphiques) |
| [PHASE-5-alertes-incidents.md](./PHASE-5-alertes-incidents.md) | Phase 5 — Politiques d’alerte, contacts, incidents |
| [PHASE-6-status-page-api.md](./PHASE-6-status-page-api.md) | Phase 6 — Status page, API, import/export |
| [PHASE-7-plus-obiora.md](./PHASE-7-plus-obiora.md) | Phase 7 — Différenciation (Doctor, Crash, forensics) |
| [ROADMAP-MONITOR-GRAFANA-DEDIE.md](./ROADMAP-MONITOR-GRAFANA-DEDIE.md) | **Roadmap Monitor+ vs Grafana**, profils dédiés génériques, lots NOC |
| [GRAFANA-PONT.md](./GRAFANA-PONT.md) | Pont Prometheus / Grafana OSS |

## Principes directeurs

1. **Ne pas recopier Pinguzo** — s’inspirer de l’UX et du modèle produit, implémenter avec la stack ObiOra (Laravel, Livewire, agents existants).
2. **Ne pas utiliser l’agent Pinguzo** — propriétaire, lié à `api.pinguzo.com` / edge Softaculous. Lecture locale sur le dédié Virtualizor **uniquement pour inspiration**.
3. **Réutiliser au maximum** — `servers`, agent slave, Crash Analyzer, ping fleet, alertes existantes.
4. **Une section Monitor unifiée** — remplacer la dispersion actuelle (Monitoring / Doctor / Crash) par un hub cohérent, avec liens vers Doctor & Suite pour le diagnostic avancé.
5. **Self-hosted** — pas de SaaS tiers obligatoire ; le panel maître est le centre de collecte.

## Ordre de livraison recommandé

```
Phase 1  →  Dashboard + liste serveurs + préférences timezone  (démarrage validé)
Phase 2  →  Moniteurs externes (sites)
Phase 3  →  Agent métriques serveur unifié
Phase 4  →  Graphiques détaillés (server metrics / monitor metrics)
Phase 5  →  Alertes + incidents
Phase 6  →  Status page + API + import/export
Phase 7  →  Intégration Doctor / Crash / CrashHunter
```

## Screens fournis (référence)

Les captures utilisateur couvrent suffisamment **Phases 1 à 5** pour rédiger les specs. Compléments utiles plus tard (optionnels) :

- Onglet **Contacts** (détail Slack/Discord/Telegram)
- **Notification Logs**
- Édition moniteur **Keyword** et **DNS** (champs spécifiques)
- Page **Import / Export** complète

## Démarrage Phase 1

Voir [PHASE-1-fondations-dashboard.md](./PHASE-1-fondations-dashboard.md) — critères d’acceptation, maquettes fonctionnelles, tables BDD, tâches techniques.
