<div>
    <div class="mb-4">
        <a href="{{ route('servers.index') }}" class="text-decoration-none small">&larr; Retour aux serveurs</a>
        <div class="d-flex justify-content-between align-items-start mt-2">
            <div>
                <h1 class="h3 mb-1">{{ $server->name }}</h1>
                <p class="text-muted mb-0">{{ $server->ip_address }} — {{ $server->type->value }}</p>
            </div>
            <div class="d-flex gap-2">
                <button wire:click="ping" class="btn btn-outline-secondary btn-sm">Ping</button>
                <button wire:click="useServer" class="btn btn-primary btn-sm">Utiliser ce serveur</button>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6">Informations</h2>
                    <dl class="row small mb-0">
                        <dt class="col-4">Statut</dt>
                        <dd class="col-8">{{ $server->status->value }}</dd>
                        <dt class="col-4">Hostname</dt>
                        <dd class="col-8">{{ $server->hostname ?? '—' }}</dd>
                        <dt class="col-4">OS</dt>
                        <dd class="col-8">{{ $server->os_name ?? '—' }}</dd>
                        <dt class="col-4">Dernière vue</dt>
                        <dd class="col-8">{{ $server->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        <dt class="col-4">Maître</dt>
                        <dd class="col-8">{{ $server->is_master ? 'Oui' : 'Non' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        @unless ($server->is_master)
            <div class="col-lg-6">
                <div class="card obiora-card border-warning">
                    <div class="card-body">
                        <h2 class="h6">Agent — token d'authentification</h2>
                        <p class="small text-muted">Copiez ce token dans <code>agent/config/agent.json</code> sur le serveur distant, puis démarrez l'agent.</p>
                        <div class="bg-light p-2 rounded small font-monospace text-break user-select-all">{{ $server->agent_token }}</div>
                        <p class="small mt-3 mb-0">
                            <code>bash {{ base_path('agent/bin/obiOra-agent') }} start</code>
                        </p>
                    </div>
                </div>
            </div>
        @endunless
    </div>
</div>
