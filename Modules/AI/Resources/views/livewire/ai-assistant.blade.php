<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">🤖 Assistant IA ObiOra</h1>
            <p class="text-muted mb-0 small">
                Contexte serveur actif, rapports Doctor et monitoring — sans exécution shell automatique.
            </p>
        </div>
        <div class="d-flex gap-2">
            @if (!$enabled)
                <span class="badge text-bg-secondary">Desactive</span>
            @elseif (!$planAllowed)
                <span class="badge text-bg-warning text-dark">Plan Pro requis</span>
            @else
                <span class="badge text-bg-success">Actif</span>
            @endif
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearChat">
                Effacer
            </button>
        </div>
    </div>

    @if (!$enabled)
        <div class="alert alert-info">
            Activez <code>OBIORA_AI_ENABLED=true</code> et configurez <code>OBIORA_AI_API_KEY</code> dans le fichier <code>.env</code>.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card obiora-card">
                <div class="card-body d-flex flex-column" style="min-height: 420px;">
                    <div class="flex-grow-1 overflow-auto mb-3 pe-1" style="max-height: 360px;">
                        @foreach ($messages as $idx => $msg)
                            <div class="mb-3 {{ $msg['role'] === 'user' ? 'text-end' : '' }}">
                                <div class="d-inline-block text-start p-2 px-3 rounded-3 small {{ $msg['role'] === 'user' ? 'bg-primary text-white' : 'bg-dark-subtle' }}"
                                     style="max-width: 92%; white-space: pre-wrap;">
                                    {{ $msg['content'] }}
                                    @if (!empty($msg['offline']))
                                        <div class="mt-1 opacity-75" style="font-size: 0.7rem;">mode local / hors ligne</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if ($thinking)
                            <div class="text-muted small">Réflexion…</div>
                        @endif
                    </div>

                    <form wire:submit="send" class="d-flex gap-2">
                        <input type="text"
                               class="form-control form-control-sm obiora-input"
                               wire:model="prompt"
                               placeholder="Ex : pourquoi mon score Doctor a baissé ?"
                               autocomplete="off"
                               @disabled($thinking)>
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="send">
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
                    <p class="mb-2">Providers : OpenAI, Anthropic, Ollama (OpenAI-compatible).</p>
                    <p class="mb-0">Les réponses sont informatives — validez toute action sensible dans le panel.</p>
                </div>
            </div>
        </div>
    </div>
</div>
