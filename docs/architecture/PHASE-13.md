# ObiOra Panel — Phase 13 : Modules métier, Suite & polish produit (v2.1.0)

> Statut : **implémentée (v2.1.0)**. Modules Infrastructure métier, Doctor/Suite,
> historique IA, actions chat, changelog intégré, fix Webmin.

## Objectif

Passer d’un panel **fonctionnel avec stubs UI** à une **seedbox gérée de bout en bout**
depuis ObiOra Panel, avec diagnostic Doctor, marketplace fiable et notes de version
visibles sans quitter l’interface.

---

## Lot A — Modules Infrastructure (logique métier)

Pages actuelles : `/modules/{slug}` (UI stub depuis Phase 11).

| Priorité | Module | Livrables cibles |
|:---:|---|---|
| 1 | **SSL / TLS** | Inventaire certificats, alertes expiration, renouvellement groupé |
| 2 | **Firewall** | UFW / firewalld : règles, ports apps marketplace, fail2ban |
| 3 | **Utilisateurs** | CRUD comptes panel, rôles Spatie, invitations, quotas licence |
| 4 | **Nginx** | Édition vhosts, upstreams, cache — au-delà du provisioning sites |
| 5 | **FTP** | Comptes Pure-FTPd / vsftpd, quotas, lien marketplace |
| 6 | **DNS** | Zones locales Bind/Unbound ou sync registrar |
| 7 | **Redis** | Statut, mémoire, flush cache, persistence |
| 8 | **Apache** | Virtual hosts httpd (alternative Nginx) |
| 9 | **Applications** | Inventaire unifié paquets système + conteneurs |
| 10 | **Virtualizor** | API provisioning VPS clients |
| 11 | **Cluster** | Multi-nœuds, basculement, sync agents |

Chaque module suit le même modèle :

- Livewire dédié (`Modules/{Name}/Livewire/…`)
- Scripts agent (`agent/scripts/…`) exécutés via sudo
- Permissions Spatie + tests Feature
- Entrée sidebar Infrastructure (section repliable)

**Correctif marketplace (v2.0.2)** : Webmin — ouverture port 10000 (firewalld/ufw),
vérification service systemd, écoute réseau (plus localhost seul).

---

## Lot B — Assistant IA (enrichissements Phase 12)

Base livrée en v2.0.1 (`/ai`, contexte Doctor, providers API).

| Fonctionnalité | Description |
|---|---|
| Streaming Reverb | Réponses token par token via WebSocket (si Reverb actif) |
| Historique BDD | Conversations persistées par utilisateur / serveur |
| Actions chat | « Redémarre nginx », « Lance un scan Doctor », liens actionnables |
| Suggestions proactives | Alertes Doctor → proposition de correctif dans le chat |

---

## Lot C — ObiOra-Doctor & ObiOra-Suite dans le panel

Projets locaux (gitignorés) aujourd’hui ; objectif : **tout piloter depuis le panel seedbox**.

| Composant | Intégration panel |
|---|---|
| **ObiOra-Doctor** | Install/màj agent, config scans, planification, rapport inline |
| **ObiOra-Suite** | Hub modules complémentaires (site vitrine, outils admin) |
| API unifiée | Endpoints panel ↔ agent Doctor signés (`OBIORA_DOCTOR_SIGNING_KEY`) |
| UI Monitoring | Fusion vue fleet + détail Doctor + actions correctives |

---

## Lot D — Doc produit & changelog intégré

| Élément | Statut |
|---|---|
| `CHANGELOG.md` v2.0.0 / v2.0.1 | À maintenir à chaque release |
| Changelog dans **Licence & MAJ** | Parser `CHANGELOG.md` → notes inline (démarré v2.0.2) |
| `PHASE-13.md` | Ce document |
| README roadmap | Mise à jour table phases 1→13 |

---

## Lot E — Reverb en production (optionnel)

Reverb **fonctionne sans configuration** en mode dégradé : le panel utilise le
**polling Livewire** et le **SSE monitoring** (comportement par défaut).

Pour le temps réel instantané, activation manuelle requise :

```env
OBIORA_REALTIME_ENABLED=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=obiora-panel
REVERB_APP_KEY=<hex32>
REVERB_APP_SECRET=<hex64>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=<domaine-panel>
REVERB_PORT=8080
REVERB_SCHEME=https
```

```bash
systemctl enable --now obiora-reverb
systemctl restart obiora-queue
```

Nginx : bloc `location /app` (voir [PHASE-11.md](PHASE-11.md)).

---

## Jalons versionnés (indicatif)

| Version | Contenu |
|---|---|
| **v2.0.2** | Fix Webmin, changelog intégré, doc Phase 13 |
| **v2.1.0** | SSL + Firewall + Utilisateurs (modules métier 1–3) |
| **v2.2.0** | IA streaming + historique + actions |
| **v2.3.0** | Doctor/Suite intégrés au panel |
| **v2.4.0+** | Modules Infrastructure restants |

---

## Tests

```bash
php artisan test --filter=ChangelogParser
php artisan test --filter=ModuleStub
php artisan test --filter=AiAssistant
# Après chaque module métier :
php artisan test --filter=Firewall
```

## Références

- [Phase 11 — Reverb](PHASE-11.md)
- [Phase 12 — Assistant IA](PHASE-12.md)
- [Phase 9 — Marketplace](PHASE-9.md)
