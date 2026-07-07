# ObiOra Panel — Phase 11 : Temps réel natif (Laravel Reverb)

> Statut : **planifiée, non implémentée**. Le panel utilise aujourd'hui du
> polling (Livewire `wire:poll` + `setInterval` JS), configurable par
> l'utilisateur (0 / 3 / 5 / 10 / 30 / 60 s) depuis le Dashboard. Cette phase
> vise à remplacer ce polling par des évènements poussés en direct.

## Objectif

Passer d'un rafraîchissement périodique à une diffusion instantanée des
métriques et évènements système via **Laravel Reverb** (serveur WebSocket
natif Laravel, sans dépendance externe type Pusher).

## Périmètre envisagé

| Domaine | Aujourd'hui (polling) | Cible (Reverb) |
|---|---|---|
| Dashboard (CPU, RAM, disque, réseau) | `wire:poll` toutes les X s | Broadcast dès que l'agent publie une nouvelle mesure |
| Services système (start/stop/restart) | Rafraîchi au polling suivant | Évènement `ServiceStateChanged` poussé immédiatement |
| Progression Marketplace / Docker / MAJ | Polling du cache de progression | Évènement `ProgressUpdated` par job (`ApplicationInstallJob`, `DockerInstallJob`, `DockerUninstallJob`, update panel) |
| Logs (services, apps) | Rechargement manuel / poll | Streaming des nouvelles lignes |

## Composants techniques prévus

1. **Serveur Reverb** : `composer require laravel/reverb`, service systemd
   dédié (`obiora-reverb.service`), port interne proxifié par Nginx (WSS).
2. **Broadcasting Laravel** : `BROADCAST_CONNECTION=reverb`, events
   `ShouldBroadcastNow` pour les mesures système et les changements d'état.
3. **Laravel Echo (front)** : remplacement progressif de `wire:poll` par des
   écouteurs `Echo.channel(...).listen(...)`, avec repli automatique sur le
   polling existant si la connexion WebSocket échoue (dégradation gracieuse,
   utile en environnement où le WebSocket est bloqué par un proxy/firewall).
4. **Émission côté agent** : l'agent (`agent/`) publie les métriques déjà
   collectées vers un event Laravel au lieu d'attendre le prochain scrape.

## Contraintes / points d'attention

- Garder le mode polling comme **fallback** (tous les environnements ne
  supportent pas les WebSockets sortants — proxys d'entreprise, certains
  reverse proxy mal configurés).
- Reverb nécessite un port dédié exposé (ou un `location` Nginx en proxy
  WebSocket) — à intégrer dans `install.sh` et le renouvellement TLS.
- Pas de régression sur les installations existantes tant que Reverb n'est
  pas activé explicitement (`OBIORA_REALTIME_ENABLED=false` par défaut).

## Prochaine phase

**Phase 12 (v2.0.0)** — Assistant IA intégré au panel.
