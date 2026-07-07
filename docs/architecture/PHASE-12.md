# ObiOra Panel — Phase 12 : Assistant IA intégré (v2.0.0)

> Statut : **planifiée**. L'interface stub `/modules/ai` est disponible depuis la
> Phase 11 ; l'assistant conversationnel arrive en v2.0.0.

## Objectif

Fournir un **assistant IA contextuel** dans ObiOra Panel seedbox, capable de :

- Interpréter les rapports **ObiOra Doctor** (score, findings, comparaisons)
- Suggérer des actions marketplace (install, restart, logs)
- Guider le dépannage services systemd / Docker
- Résumer l'état fleet monitoring (ping, alertes SSL, signatures invalides)

## Périmètre envisagé

| Domaine | Description |
|---|---|
| Chat panel | Widget flottant ou page `/modules/ai` enrichie |
| Contexte | Serveur actif, dernière install, dernier rapport Doctor |
| Actions | Propositions cliquables (liens routes panel, pas exécution auto sans confirmation) |
| Provider | API compatible OpenAI / Anthropic / local LLM (config `.env`) |
| Licence | Fonction réservée plans Pro/Enterprise (AdminLicence) |

## Dépendances

- Phase 11 Reverb (notifications temps réel des réponses streamées)
- Module Monitoring + API diagnostics (v1.9.56+)
- Module AI (`Modules/AI/`) — stub UI Phase 11

## Variables `.env` prévues

```env
OBIORA_AI_ENABLED=false
OBIORA_AI_PROVIDER=openai
OBIORA_AI_API_KEY=
OBIORA_AI_MODEL=gpt-4o-mini
OBIORA_AI_MAX_TOKENS=2048
```

## Contraintes

- Aucune clé API commitée ; chiffrement optionnel en base (`settings`)
- Journalisation des prompts sans secrets
- Mode hors-ligne : message explicite si provider indisponible
- Pas d'exécution shell directe depuis le LLM (couche `SystemExecutor` inchangée)

## Prochaine étape

Implémenter le service `AiAssistantManager`, le chat Livewire/Vue, et le streaming Reverb des tokens de réponse.
