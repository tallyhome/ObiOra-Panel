# ObiOra Panel — Phase 12 : Assistant IA intégré (v2.0.1)

> Statut : **implémentée**. Route `/ai`, chat Livewire, contexte Doctor/monitoring.

## Fonctionnalités

- Chat contextuel (serveur actif, score Doctor, alertes)
- Providers : OpenAI, Anthropic, Ollama (API compatible OpenAI)
- Mode local sans clé API (réponses guidées + liens panel)
- Restriction plan Pro/Enterprise si `OBIORA_LICENSE_ENABLED=true`

## Configuration `.env`

```env
OBIORA_AI_ENABLED=true
OBIORA_AI_PROVIDER=openai
OBIORA_AI_API_KEY=sk-...
OBIORA_AI_MODEL=gpt-4o-mini
OBIORA_AI_MAX_TOKENS=2048
# Optionnel : Ollama ou proxy
OBIORA_AI_BASE_URL=http://127.0.0.1:11434/v1
```

## Sidebar

- **Assistant IA** : entrée principale (`/ai`)
- **Infrastructure** : section repliable (état mémorisé dans le navigateur)

## Tests

```bash
php artisan test --filter=AiAssistant
```
