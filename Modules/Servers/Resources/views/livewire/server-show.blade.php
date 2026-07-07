<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <a href="{{ route('servers.index') }}" class="text-muted small text-decoration-none">&larr; Retour aux serveurs</a>
            <h1 class="h3 mb-1 mt-1">{{ $server->name }}</h1>
            <p class="text-muted mb-0">
                @if ($server->is_master)
                    <span class="badge text-bg-primary">Serveur maître</span>
                @endif
                <span class="badge text-bg-light text-dark">{{ $server->type->value }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button wire:click="ping" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">
                Ping
            </button>
            <button wire:click="useServer" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                Utiliser ce serveur
            </button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Informations</h2>
                    <dl class="row small mb-0">
                        <dt class="col-4">Hostname</dt>
                        <dd class="col-8">{{ $server->hostname }}</dd>
                        <dt class="col-4">Adresse IP</dt>
                        <dd class="col-8">{{ $server->ip_address }}</dd>
                        <dt class="col-4">Statut</dt>
                        <dd class="col-8">
                            @php
                                $badge = match($server->status->value) {
                                    'online' => 'success',
                                    'offline', 'error' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ $server->status->value }}</span>
                        </dd>
                        <dt class="col-4">OS</dt>
                        <dd class="col-8">{{ $server->os_name ?? '—' }} {{ $server->os_version }}</dd>
                        <dt class="col-4">Dernière vue</dt>
                        <dd class="col-8">{{ $server->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        @if ($server->is_master)
                            <dt class="col-4">Agent</dt>
                            <dd class="col-8">Local (port 9100)</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        @unless ($server->is_master)
            <div class="col-lg-6">
                <div class="card obiora-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Agent slave</h2>
                        <p class="small text-muted mb-2">Ce serveur a été lié via la clé API générée par <code>Slave/install.sh</code>.</p>
                        <dl class="row small mb-0">
                            <dt class="col-4">Port</dt>
                            <dd class="col-8">{{ $server->primaryNode?->port ?? 9100 }}</dd>
                            <dt class="col-4">Connexion</dt>
                            <dd class="col-8">{{ $server->primaryNode?->connection_type ?? 'agent' }}</dd>
                            <dt class="col-4">Dernier ping</dt>
                            <dd class="col-8">{{ $server->primaryNode?->last_ping_at?->diffForHumans() ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endunless

        <div class="col-12">
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Ressources sur ce serveur</h2>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('websites.index') }}" class="btn btn-outline-primary btn-sm">Sites web</a>
                        <a href="{{ route('databases.index') }}" class="btn btn-outline-primary btn-sm">Bases de données</a>
                        <a href="{{ route('services.index') }}" class="btn btn-outline-primary btn-sm">Services</a>
                        <a href="{{ route('backups.index') }}" class="btn btn-outline-primary btn-sm">Sauvegardes</a>
                    </div>
                    <p class="text-muted small mb-0 mt-3">
                        Utilisez « Utiliser ce serveur » pour basculer le contexte du panel vers cette machine.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
