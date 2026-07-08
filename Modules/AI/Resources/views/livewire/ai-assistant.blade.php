<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">Assistant IA ObiOra</h1>
            <p class="text-muted mb-0 small">
                Contexte serveur, Doctor et monitoring — sans exécution shell automatique.
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            @if (!$enabled)
                <span class="badge text-bg-secondary">Désactivé</span>
            @elseif (!$planAllowed)
                <span class="badge text-bg-warning text-dark">Plan Pro requis</span>
            @elseif ($hasApiKey)
                <span class="badge text-bg-success">IA cloud · {{ $providerLabel }}</span>
            @else
                <span class="badge text-bg-info text-dark">Mode local (sans clé)</span>
            @endif
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearChat">
                Effacer
            </button>
        </div>
    </div>

    @if (!$enabled)
        <div class="alert alert-info py-2 small">
            Activez <code>OBIORA_AI_ENABLED=true</code> dans le <code>.env</code> (activé par défaut depuis v2.1.2).
        </div>
    @elseif (!$hasApiKey)
        <div class="alert alert-secondary py-2 small mb-0">
            Sans clé API, l'assistant répond en <strong>mode local guidé</strong> (score Doctor, alertes, liens panel).
            Pour une vraie IA : DeepSeek (gratuit/cheap), Ollama (local), OpenAI ou Anthropic — voir <code>.env.example</code>.
        </div>
    @endif

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card obiora-card">
                <div class="card-body d-flex flex-column obiora-ai-chat">
                    <div class="flex-grow-1 obiora-ai-messages mb-3 pe-1">
                        @foreach ($messages as $msg)
                            <div class="mb-3 {{ $msg['role'] === 'user' ? 'text-end' : '' }}">
                                <div @class([
                                    'obiora-ai-bubble',
                                    'obiora-ai-bubble--user' => $msg['role'] === 'user',
                                    'obiora-ai-bubble--assistant' => $msg['role'] !== 'user',
                                    'obiora-ai-bubble--offline' => !empty($msg['offline']),
                                ])>
                                    {{ $msg['content'] }}
                                    @if (!empty($msg['offline']))
                                        <div class="obiora-ai-offline-tag">Réponse locale / hors ligne</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if ($thinking)
                            <div class="text-muted small ps-1">Réflexion…</div>
                        @endif
                    </div>

                    <form wire:submit="send" class="d-flex gap-2">
                        <input type="text"
                               class="form-control form-control-sm obiora-input"
                               wire:model="prompt"
                               placeholder="Ex : pourquoi mon score Doctor a baissé ?"
                               autocomplete="off"
                               @disabled($thinking || !$enabled)>
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="send" @disabled(!$enabled)>
                            Envoyer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card obiora-card mb-3">
                <div class="card-body">
                    <h2 class="h6">Raccourcis</h2>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($suggestions as $idx => $action)
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    wire:click="useSuggestion({{ $idx }})">
                                {{ $action['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <ul class="list-unstyled small mt-3 mb-0">
                        @foreach ($suggestions as $action)
                            <li class="mb-1">
                                <a href="{{ $action['route'] }}">→ {{ $action['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="card obiora-card">
                <div class="card-body small text-muted">
                    <p class="mb-2"><strong>Providers supportés</strong></p>
                    <ul class="mb-2 ps-3">
                        <li><code>deepseek</code> — API gratuite/low-cost (recommandé)</li>
                        <li><code>ollama</code> — 100 % local, sans clé cloud</li>
                        <li><code>openai</code> / <code>anthropic</code></li>
                        <li><code>moonshot</code> — Kimi (compatible OpenAI)</li>
                    </ul>
                    <p class="mb-0">Les réponses sont informatives — validez toute action sensible dans le panel.</p>
                </div>
            </div>
        </div>
    </div>
</div>
